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
`label`, `path` and `value` are filled in automatically (`addError` and
`addWarning` inject `isKey`, which flips `%label%` between "item" and "key of
item"). A placeholder whose value is `null` vanishes together with the space
before it — that is how `%path%` disappears at the root. Codes are the
`Message::*` string constants, whose docblocks list the expected variables; a
placeholder with no matching variable is left in the text verbatim (`%foo%`),
so a template/variables mismatch shows up only in the rendered message.

## `PreventMerging`: in-band metadata, handled in many places

The magic array key `Helpers::PreventMerging` (`'_prevent_merging'`) is
injected **directly into the data** to mean "replace, don't merge with the base /
default". Because it rides inside the value, **every element must detect and strip
it** — and they do so in subtly different ways:

- `Type::normalize` strips it, then **re-adds** it after recursing into items (so
  it survives normalization).
- `Type::complete` strips it and forces `$merge = false` (default not merged in).
- `Type::merge` / `Structure::merge` / `AnyOf::merge` / `Helpers::merge` strip it
  and return the value as-is (full replace), before `mergeWith()` or a mode.

This is the package's sharpest trap: a piece of control state travelling through
the payload, replicated across five sites. Any new `Schema` element must reproduce
the strip-and-honor dance or merging silently misbehaves. DI carries its own
parallel `PREVENT_MERGING` constant, so the key name is a cross-package contract.

## One transform pipeline; `assert`/`castTo` are sugar over `transform`

`before()`, `transform()`, `assert()`, and `castTo()` are **not** independent
stages. `before` runs in `normalize` (pre-validation); repeated calls chain in
declaration order, each handler receiving the previous result (`Base::$before`).
`EnumType` maps the backing value in its own `doNormalize()` after the handler,
never through `before()`, so the handler sees the raw input.
Everything else appends to a single `$transforms` list (`Base`): `castTo` is
`transform(getCastStrategy(...))` (a null follows PHP casts for a built-in type
unless written `?type`, and stays null for a class or an enum, as PHP has no such
cast), `assert` is a `transform` that reports an error
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
  kept. `Structure` coerces always (it has no `nullable()`), `TupleType` never,
  its default is `null`. Both `Type` and `Structure` skip it at
  `Context::$keepNullAt`, where an enclosing `anyOf()` accepts the null (see
  AnyOf).

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
- **A null value becomes the defaults** (`Structure::complete`): an empty block
  in NEON is null, which is the same as writing no key at all.

## TupleType specifics

- **A `Type('list')` whose items are given by position.** `Type` checks the
  list, its length (`min()`, `max()`) and the merging marker; the tuple
  overrides only the item hooks `normalizeItems()` and `validateItems()`. Each
  position goes through its own schema, positions beyond the shape through
  `otherItems()` (the rest elements) or fail as unexpected, and missing
  trailing positions get their defaults.
- **`mergeValues()` keeps the later layer wholesale** — mixing positions of two
  tuples is nonsense, so a tuple has no `mergeMode()`.
- **No default and no coercion of null.** The default is `null` and `default()`
  throws, so a missing tuple is `null` like a missing scalar, and a `null` value
  fails the list check: the positions of a tuple carry meaning and a tuple of
  nothing is not a value.
- It reports `Kind::Tuple`, which JSON Schema exports as `prefixItems` with
  `otherItems` as the rest schema (or `false`), and `extend()` appends positions
  and returns a tuple.

## AnyOf: first clean variant wins, in a throwaway context

`findAlternative` tries each variant **in order**. Schema variants are run
against a **fresh throwaway `Context` (`$dolly`)** that copies `path`,
`skipDefaults` and `isKey`; the first variant that completes with **no errors**
wins, and only then are its `warnings` and `dynamics` merged back into the real
context. Scalar variants are matched with strict `===`.

Two consequences: **order matters**, and the **errors, warnings and dynamics of
losing variants are discarded** with their dolly context. Side effects of their
callbacks (a transform, a constructor called by `castTo()`) are not: they have
already happened and nothing undoes them. On total failure, inner
errors (different path) are surfaced if any exist; otherwise a single aggregated
"expects to be A|B|C" error is produced.

**An accepted `null` beats coercion, not the order:** a variant that turns null
into an empty value of its own (a structure, an array) would otherwise claim it
before the null in the set was reached, and `nullable()` appends the null at the
end, so it could never win. When the set contains a null, `findAlternative` sets
`Context::$keepNullAt` to the current path in each dolly, and `Structure` and
`Type` skip the null-to-empty coercion at exactly that path, so such a variant
fails and the null wins. A variant that accepts null by itself (a nullable type,
`mixed`, a `before()` mapping it) still wins in order with its transforms. The
path pins the rule to the value of the anyOf itself, never to nested items.

`AnyOf::dynamic()` is a **flag, not a variant**, but it acts as the last one: a
`DynamicParameter` value is first offered to the variants in order, so one that
is itself `dynamic()` records its deferred type in `dynamics`. Only when none
takes it does it pass through to the transforms, with nothing recorded, as
there is no single expected type to defer.

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

## Merge direction and cast forks (thin)

- **`processMultiple` merges left-value-wins:** each later dataset item is the
  `value` (higher priority) merged over the accumulated `base`, so later configs
  override earlier ones. Numeric-keyed items append; string-keyed recurse.
- **Every element resolves its strategy in the same order:** the merging
  marker first, then `mergeWith()`, then a `MergeMode` where the element has
  one; `AnyOf` has none and falls back to the deep `Helpers::merge`.
  `mergeWith()` (`Base`) takes a **pure combiner** `fn($value, $base): mixed`; it
  runs only *between* layers (n−1 times, the sole layer of a single-layer dataset
  never passes through it), so it must combine, never canonicalize shape — that
  belongs to `before()`, which runs per layer. Legitimate for scalars too (bool
  OR, max, concatenation).
- **`MergeMode` is derived unless `mergeMode()` sets it** (public on
  `ArrayType` and `Structure`, protected in `Type`): `Replace` returns `$value`
  wholesale, `OverwriteKeys` merges by keys with numeric keys overwritten
  positionally, `AppendKeys` additionally appends new numeric elements.
  `Structure` derives `AppendKeys` with `otherItems`, else `OverwriteKeys`, and
  recurses through `items[key] ?? otherItems`; an `ArrayType` with an items
  schema derives `AppendKeys` and recurses through it. An array whose items
  are not described and whose mode is not set falls back to the deep
  `Helpers::merge`; an explicit mode merges it by keys only.
- **Null rule:** a `null` layer loses to an array and beats a scalar in
  `Helpers::merge` (NEON `key:` means "no opinion" against arrays), while
  `Structure::merge` lets it lose to anything (`$value ?? $base`).
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

`Expect::from($object)` reflects **constructor parameters if `__construct`
exists, otherwise properties** — a class with a constructor has its properties
ignored entirely. Per item: uninitialized property / non-optional parameter →
`required()`; a `null` default on a type that does not accept null → also
`required()` (not "default null"); an **object** default recurses into a nested
`from()`; anything else becomes `default($def)`. The type comes from
`Helpers::getPropertyType` (native type, then `@var`), falling back to `mixed`.
The result is a `Structure` with `castTo($class)` **stacked after** the
constructor's built-in `castTo('object')`, so a completed value travels
array → `stdClass` → instance through the cast fork above.

## `Type` and its kind-specific subclasses

`Type` is not final: `StringType`, `NumberType` and `ArrayType` extend it
and **add no behavior of their own** in 1.x. They exist so that `Expect::string()`,
`Expect::int()`, `Expect::listOf()` and `Expect::type('email')` return a class
that says what kind of value it is (for users, PHPStan and `describe()`), while
every `instanceof Type`, every type hint and every method called by name from a
NEON `parametersSchema` keeps working. The classification is done once, in
`Expect::type()`, from `TypeExpression::parse()`: an expression whose variants are
all strings (`'email'`, `'?string'`, `'url|uri'`) is a `StringType`, all numbers
(`'int'`, `'number'`) a `NumberType`, all array-like (`'list'`, `'int[]'`) an
`ArrayType`; anything else (`'bool'`, `'int|string'`, a class, `'numeric'`) stays a
plain `Type`. **String formats are the Validators pseudo-types** (`email`, `url`,
`identifier`, `digit`, ...): `Expect::type('url')` is a `StringType` validated by
`Validators::isUrl()` exactly as before. The subclasses deprecate the methods that
make no sense for their kind (`NumberType::pattern()`, `ArrayType::pattern()`,
`items()` outside arrays) with an `E_USER_DEPRECATED` notice. On a plain `Type`
they stay silent when the expression is a single kind (`'bool'`, a class); when it
is a union (`'int|string'`, `'scalar'`), `min()`, `max()`, `pattern()` and `items()`
raise a deprecation too (`Type::unionDeprecated`): a union takes no options of its
own, they belong to the variants of `anyOf()`. A union of one kind (`'int|float'`)
gets the kind's subclass and stays silent. The fluent setters in `Base` and `Type`
return `static` so the subclass survives chaining.

`EnumType` (`Expect::enum()`, and what `Expect::from()` hands out for an
enum-typed property) is the one subclass that changes behavior, and only by
widening: its `doNormalize()` turns a backing value into the case after the
user's `before()`, the validation itself is still `Type`'s `instanceof`, so a wrong
value still reads "expects to be Suit". An enum class in `Expect::type()` keeps
meaning plain `instanceof`, as for any other class. `Expect::from()` treats an
enum default as a value, not as an object to recurse into.

## Inspection: `describe()`, `TypeExpression` (`@internal`) and the JSON Schema export

`Type`, `Structure` and `AnyOf` report what they accept as a **plain array**:
`describe()` returns `'kind' => Kind` (a closed vocabulary), `required`,
`description` and the keys of the kind (`min`, `max`, `pattern`, `format`,
`items`, `keys`, `wrap`, `shape`, `otherItems`, `values`, `variants`, `type`,
plus `nullable`/`dynamic` on `Type` and `AnyOf`). **Child schemas are reported as they
are, not expanded**; only the variants of a `Type` union and the items of `'int[]'`
are arrays, because there is no element behind them. The array is exactly what
`JsonSchema::export()` needs and nothing more; there is no descriptor class.
`AnyOf::describe()` sorts its set: scalars into `values`, schemas into
`variants`, `null` into the `nullable` flag; the kind is `Union` if any schema
variant exists, `Enum` if only scalars do, `Null` for a lone `null`.

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
to BC sugar. `Type::describe()` = parse, then **narrow every variant** the
element's own `min()`/`max()` (intersected with a range from the expression),
`pattern()` (string variants only) and `items()` (array-like variants only) apply
to. `null` and `DynamicParameter` are flags, never variants, and `min`/`max` keep
the type-relative meaning of `validateRange`, so they live on the variants.

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
| `PreventMerging` handling | `Helpers::merge`, `Type`/`Structure`/`AnyOf` normalize/merge/complete |
| Transform/assert/castTo pipeline | `Base` (`transforms`, `doTransform`, `assert`, `castTo`) |
| Type validation & null/dynamic | `Type::complete`, `Helpers::validateType` |
| Structure object output, defaults | `Structure` (`completeDefault`, `validateItems`) |
| Union selection | `AnyOf::findAlternative` |
| Casting strategies | `Helpers::getCastStrategy` |
| DI / integration hook | `Processor::onNewContext`, `createContext` |
| Error message rendering | `Message::toString`, `Message::*` code constants |
| Key schemas, `isKey` | `Type::normalize`/`validateItems`, `Context::isKey` |
| Object-to-schema mapping | `Expect::from`, `Helpers::getPropertyType` |
| Kind-specific subclasses of `Type` | `Expect::type`, `StringType`, `NumberType`, `ArrayType`, `EnumType` |
| Inspection, JSON Schema export | `Elements/*::describe`, `Kind`, `TypeExpression::parse`, `JsonSchema::export` |
