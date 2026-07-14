# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

Small package, one coherent but tricky mechanism (the `Processor` pipeline over
`Elements/*`). Read `docs/internals.md` before editing it - the "phase" model is
subtler than it looks.

## Project Overview

**Nette Schema** validates and normalizes data structures (config files, API
inputs) through a fluent `Expect::` builder and a `Processor`.

- **PHP Version**: 8.1 - 8.5
- **Package**: `nette/schema` (dep: `nette/utils`); `master` = 2.0-dev,
  maintenance lives on `v1.x` branches

## Essential Commands

```bash
# Run all tests
vendor/bin/tester tests/Schema/ -s        # or: composer tester
vendor/bin/tester tests/Schema/Expect.structure.phpt -s

# Static analysis (PHPStan level 8)
composer phpstan
```

## Conventions

- Every file starts with `declare(strict_types=1);`; **tabs**; single quotes;
  `@internal` for implementation details, `@method` for `Expect`'s magic methods;
  deprecations use the native `#[\Deprecated]` attribute, not phpDoc;
  Nette Coding Standard.
- Tests are Nette Tester `.phpt` named `Expect.<feature>.phpt`; `checkValidationErrors()`
  asserts the expected error messages of a failing `process()`.

## Working in this repo

- **There is no `validate()` phase.** The `Schema` interface has four operations
  but `Processor` runs only two per call: `process()` = `normalize()` +
  `complete()`; `processMultiple()` = `normalize()` each item, `merge()`
  left-to-right, one `complete()`. **Validation happens inside `complete()`**;
  `merge()` is reached only via `processMultiple`. Don't trust the old "three-phase"
  description.
- **`before()` runs per dataset item; `transform()`/`assert()` run once** on the
  merged result - so a `before` sees one config layer, a `transform` sees the whole.
- **Errors accumulate in `Context`, never thrown mid-validation.** Each element's
  `complete()` is an `$isOk = $context->createChecker(); $isOk() && nextStep()`
  short-circuit chain - thread any new validation step through the checker or it
  runs on already-rejected values.
- **Merging is schema-driven** (2.0): `Schema::merge()` takes a `Context`,
  strategy resolves as `mergeWith(closure)` → `MergeMode` (`mergeMode()`) →
  recursion **only through item schemas**. Ambiguous merges (colliding arrays
  with no schema guidance) add a `Message::CannotMerge` error, never a silent
  guess. `AnyOf` probes which alternative both layers match
  (`Context::isPartial` = validation-only completion) and delegates to it.
- **`PreventMerging` (`'_prevent_merging'`) was removed** (BC break) —
  `Processor::rejectPreventMerging()` reports the key as an error; use
  `mergeMode(MergeMode::Replace)` instead.
- **Defaults are not merged into supplied arrays** (`Type::$merge = false`;
  `mergeDefaults()` is deprecated) - a partial input array stays partial.
- **`assert`/`castTo` are sugar over `transform`** - one `$transforms` list running
  in declaration order, so `->assert()->castTo()` differs from `->castTo()->assert()`.
- **`default` null is not `nullable`** (`nullable()` prepends `'null|'` to the type
  string); a `null` value coerces to `[]` when the default is an array. **`Structure`
  is required-by-default and casts to `object`, so `default()` throws on it.**
- **`AnyOf` tries variants in order in a throwaway `Context` clone** - losing
  variants' side effects (including transforms) are discarded. `DynamicParameter`
  values get **deferred** validation (recorded in `Context::dynamics` for DI) -
  don't validate them eagerly.
- User-facing how-to (the `Expect::` API, castTo/`Expect::from` object mapping,
  building complex schemas) is manual material and lives in the public web docs.
