# Schema internals

How `nette/schema` validates and normalizes data underneath, for agents editing
it. Small package, one coherent mechanism (the `Processor` pipeline over
`Elements/*`), so one file.

## The contract and where validation actually lives

`Schema` (`src/Schema/Schema.php`) declares four operations, but they are **not**
four phases. `Processor` has two public entry points and calls only two of them
per run:

- `process()` → `normalize()` then `complete()` (each followed by
  `throwsErrors()`).
- `processMultiple()` → `normalize()` each dataset item, `merge()` them
  left-to-right, then a single `complete()`.

**Validation is not a separate step — it happens inside `complete()`.** Type
checking, range, pattern, item recursion, and transforms all run
there (`Type::complete`). `merge()` is reached **only** through
`processMultiple`. `completeDefault()` runs for items missing from the input.
Reading the interface as "normalize / validate / complete" (as older docs do)
will mislead you: there is no `validate()`.

A non-local consequence for `processMultiple`: **`before()` hooks run per dataset
item** (inside each `normalize`, before the merge), but **`transform()`/`assert()`
run once** on the already-merged result (inside the single `complete`). So a
`before` sees one config layer at a time; a `transform` sees the whole.

## Error accumulation is the control-flow spine

Errors are **collected in `Context`, never thrown mid-validation** (`Context`,
`Processor::throwsErrors` — yes, with the typo — fires only between phases).
The mechanism that makes this
work is `Context::createChecker()`: it snapshots the current error count and
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
placeholder with no matching variable triggers an undefined-array-key warning
in `toString()`, so keep template and variables in sync.

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
2. **`MergeMode`** (`mergeMode()`, internal state `null` = unspecified):
   `Replace` returns `$value` wholesale; `OverwriteKeys` merges by keys with
   numeric keys overwritten positionally; `AppendKeys` additionally appends
   new numeric elements. Defaults: `Type` → `AppendKeys`; `Structure` →
   `AppendKeys` with `otherItems`, else `OverwriteKeys`.
3. **Recursion follows the schema only** — `Type` through `itemsValue`,
   `Structure` through `items[key] ?? otherItems`. A colliding key whose both
   sides are arrays but whose schema gives no guidance (no items schema, no
   explicit `mergeMode()`) adds a **`Message::CannotMerge` error** instead of
   silently picking a depth — explicit `mergeMode()` is the declared opt-out
   (colliding value then overwrites). Scalar collisions overwrite silently.
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
dataset before normalization and reports the key as a `CannotMerge` error;
the declarative replacement is `mergeMode(MergeMode::Replace)`, the NEON
`key!:` syntax is DI's job (dropping the key from earlier layers before
`processMultiple`). DI still carries its own parallel `PREVENT_MERGING`
constant and merge for `includes` handling.

## One transform pipeline; `assert`/`castTo` are sugar over `transform`

`before()`, `transform()`, `assert()`, and `castTo()` are **not** independent
stages. `before` runs in `normalize` (pre-validation) and has a **single slot**:
a second `before()` call silently replaces the first. Everything else
appends to a single `$transforms` list (`Base`): `castTo` is
`transform(getCastStrategy(...))`, `assert` is a `transform` that reports an error
and returns null on failure. They therefore execute in **declaration order** in
one `doTransform` pass, after type/range/pattern validation. Reordering
`->assert()->castTo()` vs `->castTo()->assert()` changes what each sees.

## `default` null is not `nullable`

- A `Type`'s `default` is `null`, but the type does **not** accept `null` unless
  `nullable()` was called — which works by prepending `'null|'` to the type
  string (`Type::nullable`), not by a flag. `dynamic()` similarly prepends
  `DynamicParameter::class . '|'`.
- **null-to-empty-array coercion:** `complete()` turns a `null` value into `[]`
  whenever the default is an array, with the comment "NEON cannot distinguish null
  from an empty array". The check is **unconditional — it fires even after
  `nullable()`**, so a nullable array-typed item never yields `null`, and a NEON
  key written bare (`key:`) validates as an empty array.
- **Defaults are not merged into supplied arrays** (2.0 BC break): `Type::$merge`
  defaults to `false`, so a partially supplied array no longer gets the default's
  keys merged underneath it. `mergeDefaults()` still works but is
  `#[\Deprecated]` and emits `E_USER_DEPRECATED` when enabling; its blind deep
  merge lives on only as private `Type::deepMerge()`.

## Keys validate like values — and collapse on failure

`arrayOf(value, key)` runs the key schema through the same
`normalize`/`complete` cycle as values, with `Context::isKey` set around the
call (`Type::normalize`, `Type::validateItems`); `Message::toString` renders
such errors as "key of item". The trap: a key that fails `complete()` comes
back as `null` and lands in `$res[$key ?? '']`, so **all invalid keys silently
collapse into a single `''` entry**, later ones overwriting earlier ones.

## Structure specifics

- **Required by default and casts to object.** The constructor sets
  `$required = true` and calls `castTo('object')`, so a `Structure` yields a
  `stdClass` and `default()` **throws** — it cannot have one.
- **A missing required structure still fills nested defaults.**
  `completeDefault` completes `[]` through the normal path (recursively producing
  every child's default). That path includes `doDeprecation`, so a deprecated
  structure emits its warning even when merely absent from the input.
- **`skipDefaults` has two independent switches** — the `Processor`
  (`Context::skipDefaults`) and the `Structure` — and `validateItems` fills in a
  default only when **neither** asks to skip it.

## AnyOf: first clean variant wins, in a throwaway context

`findAlternative` tries each variant **in order**. Schema variants are run
against a **fresh throwaway `Context` (`$dolly`)** that copies only `path`; the
first variant that completes with **no errors** wins, and only then are its
`warnings` merged back into the real context. The dolly does **not** inherit
`skipDefaults`/`isKey`, and even the winning variant's `dynamics` are **not**
merged back — a dynamic parameter nested inside an `anyOf` variant silently loses
its deferred validation. Scalar variants are matched with strict `===`.

Two consequences: **order matters**, and **side effects (including transforms) of
losing variants are discarded** with their dolly context. On total failure, inner
errors (different path) are surfaced if any exist; otherwise a single aggregated
"expects to be A|B|C" error is produced.

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
  override earlier ones (details in "Schema-driven merging" above).
- **`castTo` forks by target** (`Helpers::getCastStrategy`): builtin →
  `settype`; class **with** constructor → named args from the array/stdClass
  (a scalar is passed as a single argument); anything else → property assignment
  via `Arrays::toObject((array) $value, new $type)`. There is **no enum branch**:
  an enum has no constructor, falls into the `new $type` path and dies with a
  PHP `Error`. This fork is the mechanism behind both `castTo(Class::class)`
  and Structure's object output.
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
  without a default** (beware: `class_exists` is also true for enums, which
  then map badly);
- no default (uninitialized property / non-optional parameter) → `required()`;
- an **object** default recurses into a nested `from($default)` (instance-based);
- any other default — including `null` — becomes `default($def)` (the 1.x rule
  "null default on a non-nullable type → `required()`" is gone).

The result is a `Structure` with `castTo($class)` **stacked after** the
constructor's built-in `castTo('object')`, so a completed value travels
array → `stdClass` → instance through the cast fork above.

## Navigation map

| Concern | Where |
|---|---|
| Entry points, phase order | `Processor::process`, `processMultiple` |
| Error accumulation, checker idiom | `Context`, every `Elements/*::complete` |
| Merge strategies | `MergeMode`, `Base::mergeMode`/`mergeWith`, every `Elements/*::merge` |
| AnyOf probe, partial mode | `AnyOf::matches`, `Context::isPartial` |
| `_prevent_merging` guard | `Processor::rejectPreventMerging` |
| Transform/assert/castTo pipeline | `Base` (`transforms`, `doTransform`, `assert`, `castTo`) |
| Type validation & null/dynamic | `Type::complete`, `Helpers::validateType` |
| Structure object output, defaults | `Structure` (`completeDefault`, `validateItems`) |
| Union selection | `AnyOf::findAlternative` |
| Casting strategies | `Helpers::getCastStrategy` |
| DI / integration hook | `Processor::onNewContext`, `createContext` |
| Error message rendering | `Message::toString`, `Message::*` code constants |
| Key schemas, `isKey` | `Type::normalize`/`validateItems`, `Context::isKey` |
| Object-to-schema mapping | `Expect::from` (native types only) |
