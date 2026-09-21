# Migrating to Schema 2.0

Guiding principle of 2.0: where behavior had to change, you mostly get **an
exception, an error or at least a deprecation notice instead of silently
different results**. The few changes that give a different result without any
warning are marked *silent*. This guide lists every BC break and its remedy.

## The type system

- **Options live only where they mean something.** `min()` and `max()` belong
  to `NumberType`, `StringType` and `ArrayType`, `pattern()` to `StringType`,
  `items()` to `ArrayType`. Calling them on a plain `Type` (`bool`, `mixed`,
  a class, a union) throws `DeprecatedException` with advice; 1.4 raised a
  deprecation notice on a union and on a subclass of another kind (`pattern()`
  on a number), and stayed silent on `bool`, `mixed` or a class.
- **A union of different kinds is `anyOf()`.** `Expect::type('int|string')`
  and `Expect::scalar()` return an `AnyOf` of the variants; error messages
  read "int|string". A union of a single kind (`'int|float'`, `'int|null'`)
  keeps its subclass and its options.
- *Silent:* **`'?int[]'` is a nullable array of integers**, as in phpDoc; in 1.x
  it was an array of nullable integers. For that, write
  `Expect::arrayOf(Expect::int()->nullable())`.
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
- **The `'_prevent_merging'` magic key was removed.** A structure or a typed
  array containing it is rejected with an error. Use `->mergeMode(MergeMode::Replace)` in the schema;
  the NEON `key!:` syntax is handled by nette/di.

## Defaults are not merged into supplied arrays

*Silent:* `ArrayType::$mergeDefaults` defaults to `false`: a partially supplied array
stays partial, the default's keys are no longer merged underneath it. A `null`
(an empty key in NEON) is not a supplied array but the same as a missing key,
so it still gets the whole default.
`mergeDefaults()` turns the merging back on without a notice, so code supporting
both 1.x and 2.0 can keep calling it; 2.1 deprecates it.

## Removed APIs

- `Helpers::merge()`, `Helpers::PreventMerging`, `Helpers::validateType()`,
  `Helpers::getPropertyType()` and `Helpers::parseAnnotation()` (all were
  `@internal`).

## Other 2.0 changes

- *Silent:* `before()` called repeatedly chains the handlers in declaration
  order, each receiving the result of the previous one; 1.4 replaced the
  previous handler and emitted a deprecation notice.
- `Expect::from()` reads **native property types only** (phpDoc `@var` support
  removed) and accepts a class name in addition to an instance; a `null`
  default on a non-nullable type is now `default(null)`, not `required()`.
- *Silent:* `Expect::from()` maps a property typed with a class to a nested
  structure, so a missing uninitialized property is created from the defaults
  of that class; 1.x reported it as a missing item.

## New features

- `Message::CannotMerge`, the code of a failed merge.
- `default()` on a tuple, which 1.4 refused.

`Expect::tuple()`, `Expect::listOf(..., wrap: true)`, `Expect::enum()`,
`description()`, the JSON Schema export, the `MergeMode` enum with
`mergeMode()` on `ArrayType` and `Structure`, and `mergeWith(callable)` (a pure
combiner, it runs only between layers; canonicalize a layer's shape in
`before()` instead) came already in 1.4.
