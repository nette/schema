# Schema internals

How `nette/schema` validates and normalizes data underneath, for agents editing
it. Small package, one coherent mechanism (the `Processor` pipeline over
`Elements/*`), so one file.

## The contract and where validation actually lives

`Schema` (`src/Schema/Schema.php`) declares four operations, but they are **not**
four phases. `Processor` has two public entry points and calls only two of them
per run:

- `process()` → `normalize()` then `complete()` (each followed by
  `throwErrors()`).
- `processMultiple()` → `normalize()` each dataset item, `merge()` them
  left-to-right, then a single `complete()`.

**Validation is not a separate step — it happens inside `complete()`.** Type
checking, range, pattern, item recursion, default merging, and transforms all run
there (`Type::complete`). `merge()` is reached **only** through
`processMultiple`. `completeDefault()` runs for items missing from the input.
Reading the interface as "normalize / validate / complete" (as older docs do)
will mislead you: there is no `validate()`.

A non-local consequence for `processMultiple`: **`before()` hooks run per dataset
item** (inside each `normalize`, before the merge), but **`transform()`/`assert()`
run once** on the already-merged result (inside the single `complete`). So a
`before` sees one config layer at a time; a `transform` sees the whole.

## Error accumulation is the control-flow spine

Errors are **collected in `Context`, never thrown mid-validation**
(`Processor::throwErrors` fires only between phases). The mechanism that makes
this work is `Context::createChecker()`: it snapshots the current error count and
returns a closure that is `true` only while no new error has been added.

Every element's `complete()` is a chain guarded by that closure:

```php
$isOk = $context->createChecker();
Helpers::validateType(...);
$isOk() && Helpers::validateRange(...);
$isOk() && ... && $value = $this->doTransform(...);
```

**This `$isOk() && ...` short-circuit is the invariant to preserve.** Each step
runs only if every prior step stayed clean, so later logic never sees a value an
earlier check already rejected. Add a validation step without threading it through
the checker and you will validate/transform garbage. `doTransform` re-arms its own
checker so a transform that reports an error stops the remaining transforms.

`Processor::createContext()` builds a fresh `Context` per run and invokes the
public `Processor::onNewContext` closures on it — the official hook by which
other packages attach (nette/di plugs in here, e.g. to consume `dynamics`).
Reshaping `Context` or `createContext` breaks them invisibly.

## Adding errors: the `Message` contract

`Context::addError(template, code, variables)` stores a `Message`; rendering is
deferred to `Message::toString()`. Templates use `%placeholder%` substitution:
`label`, `path` and `value` are filled in automatically (`addError` injects
`isKey`, which flips `%label%` between "item" and "key of item"; `addWarning`
does not). A placeholder whose value is `null` vanishes together with the space
before it — that is how `%path%` disappears at the root. Codes are the
`Message::*` string constants, whose docblocks list the expected variables; a
placeholder with no matching variable is left in the text verbatim (`%foo%`),
so a template/variables mismatch shows up only in the rendered message.

## Schema-driven merging (2.0)

`Schema::merge(mixed $value, mixed $base, Context $context)` combines two
normalized layers, `$value` (later, higher priority) over `$base`. Errors
accumulate in the Context like everywhere else (`Processor::processMultiple`
throws after each merge, before `complete()`), and recursion maintains
`$context->path`, so merge errors carry a path.

Every element resolves its strategy in the same order:

1. **`mergeWith(closure)`** (`Base`) wins outright — a user-supplied **pure
   combiner** `fn($value, $base): mixed`. It runs only *between* layers (n−1
   times; the sole layer of a single-layer dataset never passes through it), so
   it must combine, never canonicalize shape — that belongs to `before()`.
   Legitimate for scalars too (bool OR, max, concatenation) and doubles as the
   escape hatch for blind deep merge of free-form trees.
2. **`MergeMode`** lives only where every value of the enum means something:
   `ArrayType` and `Structure`, each with its own private state and
   `mergeMode()` setter. `Replace` returns `$value` wholesale; `OverwriteKeys`
   merges by keys with numeric keys overwritten positionally; `AppendKeys`
   additionally appends new numeric elements. Defaults: `ArrayType` →
   `AppendKeys`; `Structure` → `AppendKeys` with `otherItems`, else
   `OverwriteKeys`. `TupleType` pins `Replace` in its constructor and its
   `mergeMode()` throws. An `AnyOf` has no mode; skipping the probe is
   `mergeWith(fn($value, $base) => $value)`. A plain `Type` (`mixed`, `bool`,
   instances) has no mode either: arrays that reach it merge conservatively
   (numeric keys append, colliding arrays are an error).
3. **Recursion follows the schema only** — the walk lives in
   `Type::mergeValues`/`ArrayType::mergeValues` with the per-key collision
   delegated to `mergeItem()`, which `ArrayType` overrides to recurse through
   its items schema; `Structure` recurses through `items[key] ?? otherItems`.
   A colliding key whose both sides are arrays but whose schema gives no
   guidance (no items schema, no explicit `mergeMode()`) adds a
   **`Message::CannotMerge` error** instead of silently picking a depth —
   explicit `mergeMode()` is the declared opt-out (colliding value then
   overwrites). Scalar collisions overwrite silently.
4. **Null rule (uniform):** a `null` layer value loses to an array and beats
   a scalar (`$value === null && is_array($base) ? $base : $value`) — NEON
   `key:` means "no opinion" against arrays.

**`AnyOf::merge` probes instead of merging blindly:** it finds the first
variant (declaration order) that **both** layers match and delegates to its
`merge()`. Matching runs each layer through `normalize` + `complete` in a
throwaway Context with **`Context::isPartial`** set — a validation-only mode
where `completeDefault` doesn't report missing required items (a layer is
legally partial), `doTransform` is skipped (a `castTo` constructor would
crash on a partial layer), and deprecations stay silent. No common variant:
two arrays → `CannotMerge` error; otherwise the later value wins (scalar
`proxy: string|array` overrides keep working). `DynamicParameter` on either
side → plain replace. **Known limitation:** the probe matches layers through
the variant's `normalize()`, but delegation merges the AnyOf-level values —
a variant whose `before()` reshapes layers therefore merges as plain replace
(v1-compatible). Re-normalizing for the merge is not an option: `complete()`
would then run the variant's `before()` a second time on the merged result.

## `PreventMerging` is gone; transitional guard

The v1 magic key `'_prevent_merging'` (in-band metadata meaning "replace,
don't merge") was **removed entirely** — no constant, no `Helpers::merge()`,
nothing strips it from data. So it doesn't silently flow into output as
ordinary data, `Processor::rejectPreventMerging()` recursively scans every
dataset (arrays and stdClass) before normalization and reports the key as
a `CannotMerge` error; the declarative replacement is
`mergeMode(MergeMode::Replace)`, the NEON `key!:` syntax is DI's job
(dropping the key from earlier layers before `processMultiple`). DI still
carries its own parallel `PREVENT_MERGING` constant and merge for `includes`
handling.

## One transform pipeline; `assert`/`castTo` are sugar over `transform`

`before()`, `transform()`, `assert()`, and `castTo()` are **not** independent
stages. `before` runs in `normalize` (pre-validation); repeated calls chain in
declaration order, each handler receiving the previous result (`Base::$before`).
Everything else appends to a single `$transforms` list (`Base`): `castTo` is
`transform(getCastStrategy(...))`, `assert` is a `transform` that reports an error
and returns null on failure. They therefore execute in **declaration order** in
one `doTransform` pass, after type/range/pattern validation. Reordering
`->assert()->castTo()` vs `->castTo()->assert()` changes what each sees.

## `default` null is not `nullable`

- A `Type`'s `default` is `null`, but the type does **not** accept `null` unless
  `nullable()` was called — which works by prepending `'null|'` to the type
  string (`Type::nullable`), not by a flag. `dynamic()` similarly prepends
  `DynamicParameter::class . '|'`.
- **null-to-empty-array coercion:** `Type::complete()` turns a `null` value into
  `[]` when the default is an array **and the type does not accept null** ("NEON
  cannot distinguish null from an empty array"), so a NEON key written bare
  (`key:`) validates as an empty array, while after `nullable()` the `null` is
  kept. `Structure` coerces unconditionally (it has no `nullable()`), `TupleType`
  not at all.

## Keys validate like values

`arrayOf(value, key)` runs the key schema through the same
`normalize`/`complete` cycle as values, with `Context::isKey` set around the
call (`Type::normalize`, `Type::validateItems`); `Message::toString` renders
such errors as "key of item". An item whose key fails `complete()` is **dropped
from the result** (`validateItems` keeps it only while the key's own checker
stays clean); its value is still completed first, so errors in both key and
value are reported.

## Structure specifics

- **Required by default and casts to object.** The constructor sets
  `$required = true` and calls `castTo('object')`, so a `Structure` yields a
  `stdClass` and `default()` **throws** — it cannot have one.
- **A missing required structure still fills nested defaults.**
  `completeDefault` completes `[]` through the normal path (recursively producing
  every child's default), with `deprecated` suspended for the call, so an absent
  deprecated structure does not warn; a present one does.
- **`skipDefaults` has two independent switches** — the `Processor`
  (`Context::skipDefaults`) and the `Structure` — and `validateItems` fills in a
  default only when **neither** asks to skip it.
- **A null value becomes the defaults** (`Structure::coerce`): an empty block in
  NEON is null, which is the same as writing no key at all. `TupleType`
  overrides the hook and keeps the null, so it fails the array check — the
  positions of a tuple carry meaning and a tuple of nothing is not a value.
- **`TupleType` is a `Structure` with a list shape cast to an array**
  (`Structure` is no longer final). It overrides `merge()` to keep the later
  layer wholesale — mixing positions of two tuples is nonsense — and reports
  `Kind::Tuple`, which JSON Schema exports as `prefixItems` with `otherItems`
  as the rest schema (or `false`).

## AnyOf: first clean variant wins, in a throwaway context

`findAlternative` tries each variant **in order**. Schema variants are run
against a **fresh throwaway `Context` (`$dolly`)** that copies `path`,
`skipDefaults` and `isKey`; the first variant that completes with **no errors**
wins, and only then are its `warnings` and `dynamics` merged back into the real
context. Scalar variants are matched with strict `===`.

Two consequences: **order matters**, and **side effects (including transforms) of
losing variants are discarded** with their dolly context. On total failure, inner
errors (different path) are surfaced if any exist; otherwise a single aggregated
"expects to be A|B|C" error is produced.

**An accepted `null` is the one exception to the order:** a variant that turns
null into an empty value of its own (a structure, an array) would otherwise
claim it before the null in the set was reached, and `nullable()` appends the
null at the end, so it could never win. `findAlternative` therefore answers a
null with null whenever the set contains one.

`AnyOf::dynamic()` is a **flag, not a variant**: a `DynamicParameter` value skips
`findAlternative` entirely and goes straight to the transforms. Nothing is
recorded in `dynamics`, there is no single expected type to defer.

`completeDefault` has one extra fork: when the default is itself a `Schema`
(`firstIsDefault()` with a schema variant), it delegates to that schema's
`completeDefault`.

## Deferred validation of dynamic parameters

`Type::complete`, when the completed value is a `DynamicParameter`, does
**not** validate the real type now — it records `[value, expectedType, path]`
(expectedType with the `DynamicParameter|` prefix stripped) in
`Context::dynamics` for **deferred** validation
(DI resolves these once runtime parameters are known). An agent must not "fix" this
by validating dynamics eagerly.

## Cast forks and range meanings (thin)

- **`castTo` forks by target** (`Helpers::getCastStrategy`): builtin →
  `settype`; backed enum → `from()`, a wrong value reports `TypeMismatch`
  listing the cases (a pure enum throws `InvalidStateException` when the schema
  is built); class **with** constructor → named args from the array/stdClass
  (a scalar is passed as a single argument); anything else → property assignment
  via `Arrays::toObject((array) $value, new $type)`. A failing instantiation is
  rethrown as `InvalidStateException` naming the target class. This fork is the
  mechanism behind both `castTo(Class::class)` and Structure's object output.
- **`min`/`max` mean different things by type** (`validateRange`): item count for
  arrays, character length (`unicode` type) or byte length (otherwise) for
  strings, the value itself for numbers.

## `Expect::from()` mapping rules

`Expect::from()` accepts an instance **or a class name** (since 2.0) and
reflects **constructor parameters if `__construct` exists, otherwise
properties** — a class with a constructor has its properties ignored entirely.
Types come from **native declarations only** (`Nette\Utils\Type::fromReflection`,
fallback `mixed`); phpDoc `@var` support was removed in 2.0. Per item:

- a non-nullable class-typed item recurses into `from($thatClass)` **even
  without a default** (an enum-typed item maps to `EnumType` instead);
- no default (uninitialized property / non-optional parameter) → `required()`;
- an **object** default that is not an enum case recurses into a nested
  `from($default)` (instance-based);
- any other default — including `null` — becomes `default($def)` (the 1.x rule
  "null default on a non-nullable type → `required()`" is gone).

The result is a `Structure` with `castTo($class)` **stacked after** the
constructor's built-in `castTo('object')`, so a completed value travels
array → `stdClass` → instance through the cast fork above.

## `Type` and its kind-specific subclasses

`Type` is not final: `StringType`, `NumberType` and `ArrayType` extend it and
since 2.0 **carry the options of their kind**: `min()`/`max()` on all three,
`pattern()` on `StringType`, `items()`/`keys` and the deprecated
`mergeDefaults()` on `ArrayType`. On a plain `Type` those methods **throw
`DeprecatedException`** saying who has them, closing the notices 1.4 opened.
The classification is done once, in `Expect::type()`, via `Expect::typeClass()`
from `TypeExpression::parse()`: an expression whose variants are all strings
(`'email'`, `'?string'`, `'url|uri'`) is a `StringType`, all numbers a
`NumberType`, all array-like an `ArrayType`; a **union of different kinds**
(`'int|string'`, `'scalar'`) becomes an **`AnyOf` of the variants** (built via
`TypeExpression::format()`), so its message reads "int|string"; `'bool'`, a
class or `'mixed'` stays a plain `Type`. A union of one kind (`'int|float'`,
`'int|null'`) gets the kind's subclass with working options, exactly as 1.4
promised.

**`Type` validates by itself:** `matches()` is the one place that knows every
`Kind`; `Validators::is()` is asked only about named **string formats**
(`email`, `url`, `identifier`, ...) and the deprecated legacy names. The
`complete()` of `Type` is a template the subclasses hook into: `coerce()`
(ArrayType turns null into `[]` for non-nullable types there), `validate()`
(subclasses add range/pattern/items on top of the kind check, ArrayType
completes items in `completeItems()` and drops entries with invalid keys),
`mergeDefault()` (a default is not a layer; only ArrayType's deprecated
`mergeDefaults(true)` deep-merges) and `normalizeValue()`/`mergeValues()`.

**The deprecation ladder:** what 1.4 could not deprecate without breaking BC
is deprecated **by 2.0 with a notice and still works** — a range in the
expression (`'int:1..5'`, folded into validation by `matches()`), a validator
name that is not a type (`'numeric'`, checked via `Kind::Other` →
`Validators::is`), a directly constructed plain `Type` for a kind that has its
class. 2.1 turns these notices into refusals and drops the Validators
dependency for everything but string formats (`Type::deprecatedExpressions()`
is the inventory).

`EnumType` (`Expect::enum()`, and what `Expect::from()` hands out for an
enum-typed property) is the one subclass that changes behavior, and only by
widening: a `before()` hook installed in its constructor turns a backing value
into the case, the validation itself is still `Type`'s `instanceof`, so a wrong
value still reads "expects to be Suit". An enum class in `Expect::type()` keeps
meaning plain `instanceof`, as for any other class. `Expect::from()` treats an
enum default as a value, not as an object to recurse into.

## Inspection: `describe()`, `TypeExpression` (`@internal`) and the JSON Schema export

`Type`, `Structure` and `AnyOf` report what they accept as a **plain array**:
`describe()` returns `'kind' => Kind` (a closed vocabulary), `required`,
`description` and the keys of the kind (`min`, `max`, `pattern`, `format`,
`items`, `keys`, `shape`, `otherItems`, `values`, `variants`, `type`, plus
`nullable`/`dynamic` on `Type` and `AnyOf`). **Child schemas are reported as they
are, not expanded**; only the variants of a `Type` union and the items of `'int[]'`
are arrays, because there is no element behind them. The array is exactly what
`JsonSchema::export()` needs and nothing more; there is no descriptor class.
`AnyOf::describe()` sorts its set: scalars into `values`, schemas into
`variants`, `null` into the `nullable` flag; the kind is `Union` if any schema
variant exists, `Enum` if only scalars do.

**`TypeExpression::parse()` is the single translator of the `Expect::type()`
string language** (`|`, `?`, `[]`, `name:range`, `pattern:regex`) into that array.
It follows what `Validators::is()` accepts, not what the author probably meant:
`'int[]'` is `Kind::Iterable` of ints (any iterable, keys unchecked),
`'number'` is `Kind::Number` (a kind of its own, not a union), `'scalar'` expands
to a union of bool, number and string, `'?x'`/`'null|x'` set `nullable`,
`DynamicParameter` sets `dynamic`, the string pseudo-types of Validators (`email`,
`url`, `identifier`, `digit`, ...) are `Kind::String` with the name under `format`,
**an unknown name is a class name** (`Kind::Instance`, decided by exclusion, never
by `class_exists`), and only `numeric`, `numericint`, `none`, `resource` and
intersections `A&B` become `Kind::Other` with the raw expression under `type`.
That table is also the migration table for the day the string notation is reduced
to BC sugar. `Type::describe()` = parse + `describeBase()`; a subclass merges in
its own options, with `describeRange()` tightening its bounds by a deprecated
range from the expression (both are checked, so the tighter one holds). `null`
and `DynamicParameter` are flags, never variants. A tuple (`TupleType`, a
`Structure` with a list shape, `castTo('array')` and `MergeMode::Replace`
locked in by its constructor) reports `Kind::Tuple` and exports as
`prefixItems` with `otherItems` as the rest schema or `items: false`.

`JsonSchema::export()` emits shape only (`description` yes; defaults, casts and
transforms no), passes on only the string formats JSON Schema knows (`email`,
`uri`; the rest is checked by PHP alone, like `assert()`), anchors `pattern` as
`^(?:…)$`, maps `Kind::Array` to a JSON
object unless the key type is `Kind::Int`, narrows `Kind::Iterable` to a JSON
array, and **throws `NotSupportedException` for `Instance`, `Object`, `Callable`
and `Other`** rather than emitting a schema the `Processor` would then reject. An
`Expect::array()` without an item or key type throws too: nothing says whether
it is a JSON array or object.
`JsonSchema::export()` is public API; the vocabulary it is built on — `describe()`,
`Kind`, `TypeExpression` — stays `@internal` and can still change, so keep the
export the only supported way out.

## Navigation map

| Concern | Where |
|---|---|
| Entry points, phase order | `Processor::process`, `processMultiple` |
| Error accumulation, checker idiom | `Context`, every `Elements/*::complete` |
| Merge strategies | `MergeMode`, `Base::mergeWith`, every `Elements/*::merge`, `Type::mergeValues`/`mergeItem` |
| AnyOf probe, partial mode | `AnyOf::matchesAlternative`, `Context::isPartial` |
| `_prevent_merging` guard | `Processor::rejectPreventMerging` |
| Transform/assert/castTo pipeline | `Base` (`transforms`, `doTransform`, `assert`, `castTo`) |
| Type validation & null/dynamic | `Type::validate`/`matches`, subclass `validate()` overrides |
| Structure object output, defaults | `Structure` (`completeDefault`, `validateItems`) |
| Tuples | `TupleType`, `Kind::Tuple`, `JsonSchema` prefixItems arm |
| Union selection | `AnyOf::findAlternative` |
| Casting strategies | `Helpers::getCastStrategy` |
| DI / integration hook | `Processor::onNewContext`, `createContext` |
| Error message rendering | `Message::toString`, `Message::*` code constants |
| Key schemas, `isKey` | `ArrayType::normalizeValue`/`completeItems`, `Context::isKey` |
| Object-to-schema mapping | `Expect::from` (native types only) |
| Kind-specific subclasses of `Type` | `Expect::type`, `StringType`, `NumberType`, `ArrayType`, `EnumType` |
| Inspection, JSON Schema export | `Elements/*::describe`, `Kind`, `TypeExpression::parse`, `JsonSchema::export` |
