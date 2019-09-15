Nette Schema
************

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/schema.svg)](https://packagist.org/packages/nette/schema)
[![Build Status](https://travis-ci.org/nette/schema.svg?branch=master)](https://travis-ci.org/nette/schema)
[![Coverage Status](https://coveralls.io/repos/github/nette/schema/badge.svg?branch=master)](https://coveralls.io/github/nette/schema?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nette/schema/v/stable)](https://github.com/nette/schema/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/schema/blob/master/license.md)


Introduction
============

Handy library for validating data structures against a given Schema.

Documentation can be found on the [website](https://doc.nette.org/schema).

If you like Nette, **[please make a donation now](https://nette.org/donate)**. Thank you!


Installation
============

The recommended way to install is via Composer:

```
composer require nette/schema
```

It requires PHP version 7.1 and supports PHP up to 7.4.


Usage
=====

```php
$processor = new Nette\Schema\Processor;

try {
	$normalized = $processor->process($schema, $data);
} catch (Nette\Schema\ValidationException $e) {
	echo 'Data are not valid: ' . $e->getMessage();
}

// in case of error it throws Nette\Schema\ValidationException
```

Defining schema
---------------

```php
use Nette\Schema\Expect;

$schema = Expect::structure([
    'processRefund' => Expect::bool(),
    'refundAmount' => Expect::int(),
]);

$data = [
    'processRefund' => true,
    'refundAmount' => 17,
];

$normalized = $processor->process($schema, $data); // it passes
```

If you're validating data passed, you can cast strings and booleans to the expected types defined by your schema:

```php
$schema = Expect::structure([
    'processRefund' => Expect::scalar()->castTo('bool'),
    'refundAmount' => Expect::scalar()->castTo('int'),
]);

$data = [
    'processRefund' => 1,
    'refundAmount' => '17',
];

$normalized = $processor->process($schema, $data); // it passes

is_bool($normalized->processRefund); // true
is_int($normalized->refundAmount); // true
```

By default, all properties are optional and have default value `null`, or `[]` in the case of arrays.

You can change the default value as follows:

```php
$schema = Expect::structure([
    'processRefund' => Expect::bool()->default(true), // or Expect::bool(true)
]);

$data = [];

// validates, and sets defaults for missing properties
$normalized = $processor->process($schema, $data);

// $normalized->processRefund === true;
```


Arrays of items
---------------

Array where only string items are allowed:

```php
$schema = Expect::arrayOf('string');

$processor->process($schema, ['key1' => 'a', 'key2' => 'b']); // it passes
$processor->process($schema, ['key' => 123]); // error: The option 'key' expects to be string, int 123 given.
```

Indexed array (ie. with numeric keys) where only string items are allowed:

```php
$schema = Expect::listOf('string');

$processor->process($schema, ['a', 'b']); // it passes
$processor->process($schema, ['key' => 'a']); // error, unexpected 'key'
```

Enumerated values and anyOf()
-----------------------------

The `anyOf()` is used to restrict a value to a fixed set of variants or subschemes:

```php
$schema = Expect::listOf(
	Expect::anyOf('a', true, null)
);

$processor->process($schema, ['a', true, null, 'a']); // it passes
$processor->process($schema, ['a', false]); // error: The option '1' expects to be 'a'|true|null, false given.
```

Elements can be schema:

```php
$schema = Expect::listOf(
	Expect::anyOf(Expect::string(), true, null)
);

$processor->process($schema, ['foo', true, null, 'bar']); // it passes
$processor->process($schema, [123]); // error: The option '0' expects to be string|true|null, 123 given.
```

Structures
----------

Structures are objects with defined keys. Each of these key => pairs is conventionally referred to as a “property”.

Structures accept arrays and objects and return `stdClass` objects (unless you change it with `castTo('array')` etc).

By default, all properties are optional and have default value `null`. You can define mandatory properties via `required()`:

```php
$schema = Expect::structure([
	'required' => Expect::string()->required(),
	'optional' => Expect::string(), // default is null
]);

$processor->process($schema, ['optional' => '']); // error: option 'required' is missing
$processor->process($schema, ['required' => 'foo']); // it passes, returns (object) ['required' => 'foo', 'optional' => null]
```

You can define nullable properties via `nullable()`:

```php
$schema = Expect::structure([
	'optional' => Expect::string(),
	'nullable' => Expect::string()->nullable(),
]);

$processor->process($schema, ['optional' => null]); // error: 'optional' expects to be string, null given.
$processor->process($schema, ['nullable' => null]); // it passes, returns (object) ['optional' => null, 'nullable' => null]
```

By default, providing additional properties is forbidden:

```php
$schema = Expect::structure([
	'key' => Expect::string(),
]);

$processor->process($schema, ['additional' => 1]); // error: Unexpected option 'additional'
```

The `otherItems()` is used to control the handling of extra stuff, that is, properties whose names are not listed in `Expect::structure()`:

```php
$schema = Expect::structure([
	'key' => Expect::string(),
])->otherItems(Expect::int());

$processor->process($schema, ['additional' => 1]); // it passes
```

Size and ranges
---------------

You can limit the number of elements or properties using the `min()` and `max()`:

```php
// array, at least 10 items, maximum 20 items
$schema = Expect::array()->min(10)->max(20);
```

The length of a string can be constrained using the `min()` and `max()`:

```php
// string, at least 10 characters long, maximum 20 characters
$schema = Expect::string()->min(10)->max(20);
```

Ranges of numbers are specified using a combination of `min()` and `max()`:

```php
// integer, between 10 and 20
$schema = Expect::int()->min(10)->max(20);
```

Regular expressions
-------------------

String can be restricted by regular expression using the `pattern()`:

```php
// just 9 digits
$schema = Expect::string()->pattern('\d{9}');
```

Data mapping to objects
-----------------------

Schema can be generated from class:

```php
class Config
{
	/** @var string */
	public $dsn;

	/** @var string|null */
	public $user;

	/** @var string|null */
	public $password;

	/** @var bool */
	public $debugger = true;
}

$schema = Expect::from(new Config);

$data = [
	'dsn' => 'sqlite',
	'user' => 'root'
];

$normalized = $processor->process($schema, $data);
// $normalized is Config class
// $normalized->dsn === 'sqlite'
// $normalized->user === 'root'
// $normalized->password === null
// $normalized->debugger === true
```

You can even use PHP 7.4 notation:


```php
class Config
{
	public string $dsn;
	public ?string $user;
	public ?string $password;
	public bool $debugger = true;
}

$schema = Expect::from(new Config);
```

Or use anonymous class:

```php
$schema = Expect::from(new class {
	public string $dsn;
	public ?string $user;
	public ?string $password;
	public bool $debugger = true;
});
```

Custom normalization
--------------------

```php
$schema = Expect::arrayOf('string')
	->before(function ($v) { return explode(' ', $v); });

$normalized = $processor->process($schema, 'a b c'); // it passes and returns ['a', 'b', 'c']
```

Custom constraints
------------------

```php
$schema = Expect::arrayOf('string')
	->assert(function ($v) { return count($v) % 2 === 0; }); // count must be even number

$processor->process($schema, ['a', 'b']); // it passes, 2 is even number
$processor->process($schema, ['a', 'b', 'c']); // error, 3 is not even number
```

Or

```php
$schema = Expect::string()->assert('is_file'); // file must exist
```

You can add custom description for every assert. This description will be part of error message.

```php
$schema = Expect::arrayOf('string')
	->assert(function ($v) { return count($v) % 2 === 0; }, 'Even items in array');

$processor->process($schema, ['a', 'b', 'c']); // Failed assertion "Even items in array" for option with value array.
```
