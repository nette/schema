# Migrating to Schema 2.0

Guiding principle of 2.0: where behavior had to change, you get **an exception,
an error or at least a deprecation notice instead of silently different
results**. This guide lists every BC break and its remedy.

## The type system

- **Options live only where they mean something.** `min()` and `max()` belong
  to `NumberType`, `StringType` and `ArrayType`, `pattern()` to `StringType`,
  `items()` to `ArrayType`. Calling them on a plain `Type` (`bool`, `mixed`,
  a class, a union) throws `DeprecatedException` with advice — 1.4 deprecated
  these calls with a notice.
- **A union of different kinds is `anyOf()`.** `Expect::type('int|string')`
  and `Expect::scalar()` return an `AnyOf` of the variants; error messages
  read "int|string". A union of a single kind (`'int|float'`, `'int|null'`)
  keeps its subclass and its options.
- **Validators-coupled notation is deprecated but still works** — it raises
  a notice in 2.0 and 2.1 refuses it: a range in the expression
  (`'int:1..5'` → use `min()`/`max()`), validator names that are not types
  (`numeric`, `numericint`, `resource`, `none` → use `assert()`), and direct
  construction of a plain `Type` for an expression that has its subclass
  (→ use `Expect::type()`). String formats such as `email` or `url` stay.

## Merging of configuration layers (processMultiple)

Merging is now **driven by the schema**, not by blind array mechanics.

- **`Schema::merge()` signature changed** to
  `merge(mixed $value, mixed $base, Context $context): mixed`. Custom `Schema`
  implementations must add the parameter (and the return type hints added
  across the interface).
- **Ambiguous merges fail loudly.** When two layers collide on a key holding
  arrays on both sides and the schema does not describe the items, processing
  fails with *"Cannot merge …"* instead of silently deep-merging (v1) or
  overwriting. Remedies:
  - describe the data: `Expect::arrayOf(...)`, `Expect::structure(...)`,
    `otherItems()`;
  - or declare the strategy: `->mergeMode(MergeMode::AppendKeys)` (v1-like),
    `OverwriteKeys`, or `Replace`;
  - or supply a custom combiner: `->mergeWith(fn($value, $base) => ...)` —
    also the way to get a blind deep merge back if you really want it.
- **`AnyOf` no longer merges blindly**: layers merge according to the
  alternative they both match; layers matching different alternatives fail
  with an error when both are arrays (a scalar layer still simply replaces).
  This fixes nette/database#223-class bugs.
- **The `'_prevent_merging'` magic key was removed.** Data containing it is
  rejected with an error. Use `->mergeMode(MergeMode::Replace)` in the schema;
  the NEON `key!:` syntax is handled by nette/di.

## Defaults are not merged into supplied arrays

`ArrayType::$mergeDefaults` defaults to `false`: a partially supplied array
stays partial, the default's keys are no longer merged underneath it.
`mergeDefaults()` still works but is deprecated and will be removed in the
next major version.

## Removed APIs

- `Helpers::merge()`, `Helpers::PreventMerging` and `Helpers::validateType()`
  (all were `@internal`).
- The deprecated private helpers of `Base` (`doValidate`, `doValidateRange`,
  `doFinalize`).

## Other 2.0 changes

- `Schema` interface methods have native return type hints.
- `Expect::from()` reads **native property types only** (phpDoc `@var` support
  removed) and accepts a class name in addition to an instance; a `null`
  default on a non-nullable type is now `default(null)`, not `required()`.

## New features

- `MergeMode` enum + `mergeMode()` on `ArrayType` and `Structure`.
- `mergeWith(callable)`: custom merge strategy (a pure combiner — it runs only
  between layers; canonicalize a layer's shape in `before()` instead).
- `Expect::tuple([...])`: fixed-size array with per-position schemas; layers
  replace the tuple wholesale, `otherItems()` adds rest elements, and the
  JSON Schema export emits `prefixItems`.
- `Expect::listOf(..., wrap: true)`: a single value is also accepted and
  wrapped into a one-item list — layers of single values then merge by
  appending.
