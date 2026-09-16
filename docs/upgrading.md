# Upgrading nette/schema

## 1.3.7

- **Changed** `Nette\Schema\Expect::from()` now throws `Nette\InvalidArgumentException` for a class whose constructor has a variadic parameter; previously `ReflectionException` escaped; affects code catching it (9bb50bcf)
- **Changed** `Nette\Schema\Expect::from()` now reflects only public non-static properties of a class without constructor; previously private, protected and static properties became items of the structure too, so input containing such a key is now reported as an unexpected item (59490bef)
- **Changed** `Nette\Schema\Elements\Type::complete()` now reports a validation error when the key schema turns a key into something other than int or string; previously an array or object key made it fail with `TypeError` and `null`, bool or float keys were silently cast by PHP (793eae0c)
- **Changed** `Nette\Schema\Elements\Type::pattern()` now throws `Nette\InvalidStateException` when the pattern does not compile; previously every value failed as a pattern mismatch next to a PHP warning; affects code catching `Nette\Schema\ValidationException` (c1162897)

## 1.3.6

- **Changed** `Nette\Schema\Elements\Base::castTo()` now throws `Nette\InvalidStateException` when the target type is a pure (non-backed) enum; previously a plain `Error` escaped from the instantiation attempt; affects code catching it (5c0d6292)
- **Changed** `Nette\Schema\Elements\Base::castTo()` now catches a failure of the target class constructor and rethrows it as `Nette\InvalidStateException`; previously the original `Error` propagated; affects code catching it (8099d4d3)
- **Changed** `Nette\Schema\Elements\Type::complete()` now drops an item whose key fails the key schema; previously the invalid key was coerced and several such items collapsed into one entry, silently overwriting each other (594b7314)
- **Changed** `Nette\Schema\Elements\Type::complete()` now keeps `null` for a nullable schema with an array default; previously `null` was coerced to an empty array and merged with the default (c5435043)
- **Changed** `Nette\Schema\Elements\Base::deprecated()` no longer reports a deprecation for a required item that is missing from the input and takes its default value; previously the warning fired although the item was never supplied (3e0f121f)
- **Changed** `Nette\Schema\Elements\AnyOf::complete()` now propagates the matching alternative's skipDefaults, isKey and detected dynamic parameters into the parent context; previously they were lost, so dynamic parameters used inside `Nette\Schema\Expect::anyOf()` were not recognized and skipDefaults was not honored in the inner schema (510213cb)

## 1.3.4

- **Changed** `Nette\Schema\Elements\Base::assert()` now uses an empty string passed as `$description` literally; previously an empty description was replaced by the generated one (b613d629)

## 1.3.2

- **Renamed** `$items` → `$shape` of `Nette\Schema\Expect::structure()`; affects calls using named arguments; the old name was removed (8de8c4e8)
- **Renamed** `$items` → `$shape` of `Nette\Schema\Elements\Structure::__construct()`; affects calls using named arguments; the old name was removed (8de8c4e8)
- **Changed** `Nette\Schema\Expect::array()` now takes a shape and returns `Nette\Schema\Elements\Structure` casted to array when the passed array contains `Nette\Schema\Schema` instances; previously the argument was only a default value and the result was always `Nette\Schema\Elements\Type` (da801d52)
- **Changed** `Nette\Schema\Elements\Structure::merge()` now merges arrays by overwriting keys as associative arrays when `Nette\Schema\Elements\Structure::otherItems()` was not called; previously numbered items were always merged as a list (f441aec1)
- **Changed** `Nette\Schema\Elements\Structure::merge()` now overwrites a key present in the base value but covered by no item schema and no otherItems instead of deep-merging it, and merging `null` onto a non-array base value now returns the base value instead of `null` (d00e9441)

## 1.3.0

- **Deprecated** `Nette\Schema\Message::TYPE_MISMATCH`: use `Nette\Schema\Message::TypeMismatch` (alias since 1.2.4); the old name is silently deprecated (94b3ea03)
- **Deprecated** `Nette\Schema\Message::VALUE_OUT_OF_RANGE`: use `Nette\Schema\Message::ValueOutOfRange` (alias since 1.2.4); the old name is silently deprecated (94b3ea03)
- **Deprecated** `Nette\Schema\Message::LENGTH_OUT_OF_RANGE`: use `Nette\Schema\Message::LengthOutOfRange` (alias since 1.2.4); the old name is silently deprecated (94b3ea03)
- **Deprecated** `Nette\Schema\Message::PATTERN_MISMATCH`: use `Nette\Schema\Message::PatternMismatch` (alias since 1.2.4); the old name is silently deprecated (94b3ea03)
- **Deprecated** `Nette\Schema\Message::FAILED_ASSERTION`: use `Nette\Schema\Message::FailedAssertion` (alias since 1.2.4); the old name is silently deprecated (94b3ea03)
- **Deprecated** `Nette\Schema\Message::MISSING_ITEM`: use `Nette\Schema\Message::MissingItem` (alias since 1.2.4); the old name is silently deprecated (94b3ea03)
- **Deprecated** `Nette\Schema\Message::UNEXPECTED_ITEM`: use `Nette\Schema\Message::UnexpectedItem` (alias since 1.2.4); the old name is silently deprecated (94b3ea03)
- **Deprecated** `Nette\Schema\Message::DEPRECATED`: use `Nette\Schema\Message::Deprecated` (alias since 1.2.4); the old name is silently deprecated (94b3ea03)
- **Signature** native parameter and return types were added across the package; affects implementations of `Nette\Schema\Schema::normalize()`, `Nette\Schema\Schema::merge()` and `Nette\Schema\Schema::complete()`, and callers passing a value of another type to `Nette\Schema\Expect::from()`, `Nette\Schema\Expect::listOf()`, `Nette\Schema\Expect::arrayOf()`, `Nette\Schema\Elements\Structure::otherItems()`, `Nette\Schema\Processor::process()` and `Nette\Schema\Processor::processMultiple()` (bc503d43)
- **Signature** `Nette\Schema\Context`, `Nette\Schema\Expect`, `Nette\Schema\Processor`, `Nette\Schema\Message`, `Nette\Schema\Elements\Type`, `Nette\Schema\Elements\Structure` and `Nette\Schema\Elements\AnyOf` no longer use `Nette\SmartObject`; accessing an undefined property or method follows plain PHP semantics instead of throwing `Nette\MemberAccessException` (1e70164c)
- **Changed** `Nette\Schema\Expect::from()` now derives the schema from the constructor parameters (their types, optionality and defaults) when the class declares a constructor; previously it always read the object's properties (22f76752)
- **Changed** `Nette\Schema\Helpers::formatValue()` (marked @internal) now names types in error messages with `get_debug_type()` (`int`, `float`, `bool`, `null`); previously with `gettype()` (`integer`, `double`, `boolean`, `NULL`); affects code matching on message text (9df7499b)

## 1.2.5

- **Changed** `Nette\Schema\Elements\Base::castTo()` now creates an object of a class that declares a constructor through that constructor (an array of values is passed as named arguments, a single value as one argument); previously every class was instantiated with `new` and the values were assigned directly to public properties (0462f016)
- **Changed** `Nette\Schema\Context::$dynamics` entries now have a third element, the path of the value; previously each entry was a pair; affects code destructuring them (dc281416)

## 1.2.4

- **Renamed** `Nette\Schema\Message::TYPE_MISMATCH` → `Nette\Schema\Message::TypeMismatch`; the old name still works as an alias (9189c656)
- **Renamed** `Nette\Schema\Message::VALUE_OUT_OF_RANGE` → `Nette\Schema\Message::ValueOutOfRange`; the old name still works as an alias (9189c656)
- **Renamed** `Nette\Schema\Message::LENGTH_OUT_OF_RANGE` → `Nette\Schema\Message::LengthOutOfRange`; the old name still works as an alias (9189c656)
- **Renamed** `Nette\Schema\Message::PATTERN_MISMATCH` → `Nette\Schema\Message::PatternMismatch`; the old name still works as an alias (9189c656)
- **Renamed** `Nette\Schema\Message::FAILED_ASSERTION` → `Nette\Schema\Message::FailedAssertion`; the old name still works as an alias (9189c656)
- **Renamed** `Nette\Schema\Message::MISSING_ITEM` → `Nette\Schema\Message::MissingItem`; the old name still works as an alias (9189c656)
- **Renamed** `Nette\Schema\Message::UNEXPECTED_ITEM` → `Nette\Schema\Message::UnexpectedItem`; the old name still works as an alias (9189c656)
- **Renamed** `Nette\Schema\Message::DEPRECATED` → `Nette\Schema\Message::Deprecated`; the old name still works as an alias (9189c656)

## 1.2.3

- **Changed** `Nette\Schema\Message::toString()` now joins path segments with a guillemet surrounded by non-breaking spaces; previously ordinary spaces were used; affects code matching on message text (99fabf37)

## 1.2.0

- **Renamed** `$type` → `$valueType` of `Nette\Schema\Expect::arrayOf()`; affects calls using named arguments; the old name was removed (c74bf99c)
- **Changed** `Nette\Schema\Expect::anyOf()` now throws `Nette\InvalidStateException` when called with no alternatives; previously an empty set was accepted silently (89c661db)

## 1.1.0

- **Replaced** the `stdClass` items of `Nette\Schema\Context::$errors` with `Nette\Schema\Message`: read the `message`, `path` and `variables` properties instead of the ad-hoc ones such as `expected` (e74dbbcf)
- **Signature** `Nette\Schema\Context::addError()`: the second parameter is now a required error code such as `Nette\Schema\Message::TYPE_MISMATCH`, the third is an array of message placeholders (pass the previous `$expected` as `['expected' => $expected]`), and the method returns `Nette\Schema\Message` (e74dbbcf, 56814ea6, 1f2630f8)
- **Signature** `$messages` of `Nette\Schema\ValidationException::__construct()` now takes `Nette\Schema\Message` objects instead of strings; `Nette\Schema\ValidationException::getMessages()` converts them back to strings and fails on plain strings (a17dd69d)
- **Changed** `Nette\Schema\Message::toString()` now builds the text by substituting `%name%` placeholders from the variables and formats the values with `Nette\Schema\Helpers::formatValue()`, which truncates strings longer than 15 characters; previously the values were interpolated directly; affects code matching on message text (1f2630f8)
- **Changed** `Nette\Schema\Message::$code` of a range or length violation is now `Nette\Schema\Message::VALUE_OUT_OF_RANGE` or `Nette\Schema\Message::LENGTH_OUT_OF_RANGE` with its own message text; previously both were reported as `Nette\Schema\Message::TYPE_MISMATCH` (80eeb304)
- **Changed** `Nette\Schema\Message::TYPE_MISMATCH` messages are now generated from a fixed template and the expected type reads `or` and `in range` instead of `|` and `:`; previously the text came from `Nette\Utils\Validators::assert()`; affects code matching on message text (48d6dd1a)
- **Changed** `Nette\Schema\Message::toString()` now words messages with "item" where it used "option" (`The item %path% ...`, `Unexpected item`); affects code matching on message text (a0c75a44)
- **Changed** `Nette\Schema\Elements\Structure::complete()` now reports one error per unexpected key, each with its own path; previously all unexpected keys were listed in a single combined message (d19a1604)
- **Changed** `Nette\Schema\Elements\Structure::completeDefault()` now returns `null` for a structure marked `required(false)` whose value is missing; previously it returned a fully completed structure with the nested defaults applied (80847f60)
- **Changed** `Nette\Schema\Elements\Type::complete()` no longer merges an array carrying the prevent-merging flag (the `key!:` notation in NEON) with the schema defaults; previously the defaults were merged in despite the flag (3b6b9702)

## 1.0.4

- **Signature** `$min` of `Nette\Schema\Elements\Structure::min()` is now `?int`; previously `?float` (0147fe89)
- **Signature** `$max` of `Nette\Schema\Elements\Structure::max()` is now `?int`; previously `?float` (0147fe89)
- **Changed** `Nette\Schema\Elements\AnyOf::complete()` now runs the normalization of each alternative before completing it, so casting and `before()` hooks apply to items nested in `Nette\Schema\Expect::anyOf()`; previously such items were validated unnormalized (2ac00acd)
- **Changed** `Nette\Schema\Elements\Type::pattern()` is no longer applied to a `null` value; previously `null` went through the pattern check and usually failed it (108ad30b)

## 1.0.3

- **Deprecated** `Nette\Schema\Elements\Type::items()` (marked @internal): use `Nette\Schema\Expect::arrayOf()` for an array schema or `Nette\Schema\Expect::listOf()` for a list schema; the old name is silently deprecated (af8e831d)

## 1.0.1

- **Changed** `Nette\Schema\Elements\Structure::normalize()` now casts an object value to an array and normalizes its items already in the normalization phase; previously the cast happened just before validation, so nested item schemas never ran their normalization for object input (5e303420)
