# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Nette Schema** is a validation and normalization library for data structures. It provides a fluent API for defining schemas and validating configuration files, API inputs, and other structured data.

- **Package:** nette/schema
- **PHP Support:** 8.1 - 8.5
- **Dependencies:** nette/utils ^4.0
- **Documentation:** https://doc.nette.org/schema

## Development Commands

### Testing

```bash
# Run all tests
composer run tester

# Run specific test file
vendor/bin/tester tests/Schema/Expect.structure.phpt -s

# Run tests in specific directory
vendor/bin/tester tests/Schema/ -s
```

The `-s` flag shows test output (useful for debugging).

### Static Analysis

```bash
# Run PHPStan (level 8)
composer run phpstan
```

### Code Quality

- All PHP files must include `declare(strict_types=1)`
- PHPStan level 8 static analysis is enforced
- Follow Nette Coding Standard (based on PSR-12)
- Use tabs for indentation
- Use single quotes for strings

## Architecture Overview

### Core Schema Interface

The library is built around the `Schema` interface with four key operations:

```php
interface Schema {
    normalize(mixed $value, Context $context);    // Input normalization
    merge(mixed $value, mixed $base);             // Merging configs
    complete(mixed $value, Context $context);     // Validation & finalization
    completeDefault(Context $context);            // Default value handling
}
```

### Schema Implementations

1. **Type** (`Elements\Type`) - Scalar types, arrays, lists
2. **Structure** (`Elements\Structure`) - Object-like structures with defined properties (returns `stdClass`)
3. **AnyOf** (`Elements\AnyOf`) - Union types / enumerations

**Note:** Since v1.3.2, `Expect::array()` is available for array schemas with defined keys (similar to Structure but returns arrays). Useful for tuples with indexed positions.

### Fluent API Pattern

The `Expect` class provides the main API using magic methods (`__callStatic`):

```php
Expect::string()           // Created via __callStatic
Expect::int()
Expect::structure([...])
Expect::anyOf('a', 'b')
Expect::arrayOf('string')
```

All schema elements support method chaining via the `Base` trait:
- `required()` - Make field mandatory
- `default($value)` - Set default value
- `nullable()` - Allow null values
- `before($fn)` - Pre-normalization hook
- `transform($fn)` - Post-validation transformation (v1.2.5+)
- `assert($fn, $description)` - Custom validation with optional description
- `castTo($type)` - Type casting
- `deprecated($msg)` - Deprecation warnings
- `min($val)` / `max($val)` - Range constraints
- `pattern($regex)` - Regex validation

**Structure-specific methods:**
- `skipDefaults()` - Omit properties with default values from output
- `otherItems($schema)` - Allow extra items validated by schema
- `getShape()` - Get array of all structure properties
- `extend($items)` - Create new structure by extending existing one

**AnyOf-specific methods:**
- `firstIsDefault()` - Make first variant the default (instead of null)

**ArrayOf/ListOf-specific methods:**
- `mergeDefaults(false)` - Disable merging defaults with input (v1.1+)

### Three-Phase Processing

The `Processor` class executes validation in three phases:

1. **Normalize** - Transform input data (via `before()` hooks)
2. **Validate** - Check against schema (collect errors in Context)
3. **Complete** - Apply defaults, finalize values (via `transform()`)

### Context-Based Error Collection

The `Context` class accumulates errors during validation instead of throwing immediately:
- Tracks path for nested structures
- Collects all errors before throwing
- Separate warnings collection for deprecations
- `createChecker()` method allows early termination on first error

## Testing Conventions

### Test File Structure

Tests use Nette Tester with `.phpt` extension and the `test()` helper function:

```php
<?php
declare(strict_types=1);

use Tester\Assert;
use Nette\Schema\Expect;
use Nette\Schema\Processor;

require __DIR__ . '/../bootstrap.php';

test('descriptive test name', function () {
    $schema = Expect::string();
    $processor = new Processor;
    Assert::same('hello', $processor->process($schema, 'hello'));
});

testException('throws exception for invalid input', function () {
    $schema = Expect::int();
    (new Processor)->process($schema, 'invalid');
}, ValidationException::class);
```

### Key Testing Helpers

**checkValidationErrors()** - Validates exception messages:

```php
checkValidationErrors(
    fn() => (new Processor)->process($schema, $data),
    ['Expected error message']
);
```

**Test naming:**
- Use `test()` function with clear description as first parameter
- Do NOT add comments before `test()` calls
- Group related tests in the same file

## Key Implementation Details

### Magic Method Schema Creation

The `Expect` class uses `__callStatic` to create Type schemas:

```php
public static function __callStatic(string $name, array $args): Type
{
    return (new Type($name))->default($args[0] ?? null);
}
```

This allows `Expect::string()`, `Expect::email()`, `Expect::unicode()`, etc.

### Structure vs Array Schemas

**Structure** (`Expect::structure([...])`) - Returns `stdClass` objects:
- Accepts arrays and objects as input
- All properties optional by default (default: null)
- Use `required()` for mandatory properties
- Use `otherItems($schema)` to allow extra items
- Use `skipDefaults()` to omit default values from output
- Use `extend($items)` to create new structures from existing ones
- Use `getShape()` to retrieve all properties

**Array** (`Expect::array([...])`) - Returns arrays (v1.3.2+):
- Same behavior as Structure but output is array
- Can define tuples with indexed positions:
  ```php
  Expect::array([Expect::int(), Expect::string(), Expect::bool()])
  ```

### Array vs List Distinction

- `arrayOf()` - Associative or indexed arrays
- `listOf()` - Strictly indexed arrays (0, 1, 2, ...)

Both validate element types and optionally key types (since v1.2).

### Merging Behavior

The `merge()` operation combines multiple configurations:
- Arrays are merged recursively
- Structures merge property-by-property
- Special `PreventMerging` key disables merging for a value
- Used by `Processor::processMultiple()`

## Common Patterns

### Creating Complex Schemas

```php
$schema = Expect::structure([
    'database' => Expect::structure([
        'host' => Expect::string()->default('localhost'),
        'port' => Expect::int()->min(1)->max(65535)->default(3306),
        'credentials' => Expect::structure([
            'username' => Expect::string()->required(),
            'password' => Expect::string()->required(),
        ]),
    ]),
    'features' => Expect::arrayOf('bool'),
    'mode' => Expect::anyOf('development', 'production')->default('development'),
]);
```

### Validation with Custom Logic

```php
Expect::string()
    ->assert(fn($s) => strlen($s) > 0, 'String cannot be empty')
    ->transform(fn($s) => trim($s));
```

### Transform with Validation

`transform()` can both validate and modify values using Context:

```php
Expect::string()->transform(function (string $s, Nette\Schema\Context $context) {
    if (!ctype_lower($s)) {
        $context->addError('All characters must be lowercased', 'my.case.error');
        return null;
    }
    return strtoupper($s);
});
```

### Extending Structures

```php
$base = Expect::structure([
    'name' => Expect::string(),
    'age' => Expect::int(),
]);

$extended = $base->extend([
    'email' => Expect::string(),
]);
```

### Casting to Classes

**Class without constructor** - Properties are assigned:
```php
class Info {
    public bool $processRefund;
    public int $refundAmount;
}

Expect::structure([...])->castTo(Info::class);
// Creates: $obj = new Info; $obj->processRefund = ...; $obj->refundAmount = ...;
```

**Class with constructor** - Named parameters passed:
```php
class Info {
    public function __construct(
        public bool $processRefund,
        public int $refundAmount,
    ) {}
}

Expect::structure([...])->castTo(Info::class);
// Creates: new Info(processRefund: ..., refundAmount: ...)
```

**Scalar to class** - Value passed to constructor:
```php
Expect::string()->castTo(DateTime::class);
// Creates: new DateTime($value)
```

### Object Mapping

Generate schema from class definition:

```php
class Config {
    public string $name;
    public ?string $password;
    public bool $admin = false;
}

$schema = Expect::from(new Config);
// Optionally override specific fields:
$schema = Expect::from(new Config, [
    'name' => Expect::string()->pattern('\w+'),
]);
```

## File Organization

### Source Code (`src/Schema/`)

- **Expect.php** - Main API entry point (fluent builder)
- **Processor.php** - Validation engine
- **Schema.php** - Core interface
- **Context.php** - Error collection and path tracking
- **Message.php** - Error/warning messages with templating
- **ValidationException.php** - Exception with message collection
- **Helpers.php** - Internal utilities (not public API)
- **DynamicParameter.php** - Marker interface for runtime parameters
- **Elements/** - Schema implementations (Type, Structure, AnyOf, Base trait)

### Tests (`tests/Schema/`)

Test files follow the pattern `Expect.<feature>.phpt`:
- `Expect.structure.phpt` - Structure validation
- `Expect.anyOf.phpt` - Union types
- `Expect.scalars.phpt` - Primitive types
- `Expect.assert.phpt` - Custom assertions
- `Expect.transform.phpt` - Transformations
- `Helpers.*.phpt` - Internal helper tests

## Documentation Style

- Minimal phpDoc - only when adding value beyond types
- Use `@internal` for implementation details
- Use `@method` for magic methods in Expect class
- No redundant documentation of obvious signatures
- Focus on "why" not "what" for complex logic

## Common Gotchas

1. **Default value vs nullable**: Default `null` doesn't mean input can be `null` - use `nullable()` explicitly
2. **Array merging**: Default values in `arrayOf()` are merged with input unless `mergeDefaults(false)` is used (v1.1+)
3. **Pattern matching**: `pattern()` matches the entire string (automatically wrapped in `^` and `$`)
4. **AnyOf variants**: Pass variants as separate arguments, not array (use unpacking if needed: `anyOf(...$array)`)
5. **AnyOf default**: Default is `null` unless you use `firstIsDefault()` to make first variant the default
6. **Transform vs assert**: `transform()` can both validate and modify using Context; `assert()` only validates
7. **Structure vs Array**: `structure()` returns `stdClass`, `array()` returns array (v1.3.2+)
8. **Structure output**: Use `skipDefaults()` to omit properties with default values
9. **Operation order**: `assert()`, `transform()`, and `castTo()` execute in declaration order
