<?php declare(strict_types=1);

/**
 * PHPStan type tests.
 */

use Nette\Schema\Elements\AnyOf;
use Nette\Schema\Elements\ArrayType;
use Nette\Schema\Elements\NumberType;
use Nette\Schema\Elements\StringType;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use function PHPStan\Testing\assertType;


// Magic methods return Type or its dedicated subclass
assertType(StringType::class, Expect::string());
assertType(NumberType::class, Expect::int());
assertType(Type::class, Expect::bool());
assertType(NumberType::class, Expect::float());
assertType(Type::class, Expect::scalar());
assertType(Type::class, Expect::null());
assertType(Type::class, Expect::mixed());
assertType(ArrayType::class, Expect::list());
assertType(StringType::class, Expect::email());
assertType(StringType::class, Expect::unicode());

// Explicit type creation
assertType(Type::class, Expect::type('string'));
assertType(ArrayType::class, Expect::arrayOf('string'));
assertType(ArrayType::class, Expect::listOf('string'));

// Structure creation
assertType(Structure::class, Expect::structure(['a' => Expect::string()]));
assertType(Structure::class, Expect::from(new stdClass));

// AnyOf creation
assertType(AnyOf::class, Expect::anyOf('a', 'b'));

// Expect::array() conditional return (via PHPStan extension); the extension in nette/phpstan-rules
// still answers Type for the no-shape case and has to learn ArrayType
assertType(Structure::class, Expect::array(['a' => Expect::string()]));

// Fluent methods preserve concrete type
assertType(StringType::class, Expect::string()->required());
assertType(StringType::class, Expect::string()->nullable());
assertType(StringType::class, Expect::string()->default('foo'));
assertType(NumberType::class, Expect::int()->min(0)->max(100));

assertType(Structure::class, Expect::structure([])->required());
assertType(Structure::class, Expect::structure([])->otherItems('string'));
assertType(Structure::class, Expect::structure([])->skipDefaults());

assertType(AnyOf::class, Expect::anyOf('a', 'b')->required());
assertType(AnyOf::class, Expect::anyOf('a', 'b')->firstIsDefault());

// Structure::extend() returns Structure
assertType(Structure::class, Expect::structure([])->extend(['b' => Expect::int()]));
