# Migrating to Schema 2.0

Guiding principle of 2.0: where behavior had to change, you get **an exception
or error instead of silently different results**. This guide lists every BC
break and its remedy.

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

`Type::$merge` defaults to `false`: a partially supplied array stays partial,
the default's keys are no longer merged underneath it. `mergeDefaults()` still
works but is deprecated and will be removed in the next major version.

## Removed APIs

- `Helpers::merge()` and `Helpers::PreventMerging` (both were `@internal`).

## Other 2.0 changes (pre-dating the merge overhaul)

- `Schema` interface methods have native return type hints.
- `Expect::from()` reads **native property types only** (phpDoc `@var` support
  removed) and accepts a class name in addition to an instance; a `null`
  default on a non-nullable type is now `default(null)`, not `required()`.

## New features

- `MergeMode` enum + `mergeMode()` on all elements.
- `mergeWith(callable)`: custom merge strategy (a pure combiner — it runs only
  between layers; canonicalize a layer's shape in `before()` instead).
- `Expect::tuple([...])`: fixed-size array with per-position schemas; layers
  replace the tuple wholesale.
