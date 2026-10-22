<?php declare(strict_types=1);

/**
 * PHPStan type tests.
 */

use Nette\Schema\Elements\{AnyOf, ArrayType, EnumType, NumberType, StringType, Structure, TupleType, Type};
use Nette\Schema\{Expect, MergeMode};
use function PHPStan\Testing\assertType;


enum Suit: string
{
	case Hearts = 'h';
}

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

// Expect::type() narrowed by the type expression (via PHPStan extension)
assertType(StringType::class, Expect::type('string'));
assertType(NumberType::class, Expect::type('int|float'));
assertType(ArrayType::class, Expect::type('string[]'));
assertType(Type::class, Expect::type('int|string'));
assertType(Type::class, Expect::type(stdClass::class));

function testTypeFromVariable(string $type): void
{
	assertType(Type::class, Expect::type($type));
}


// Explicit type creation
assertType(ArrayType::class, Expect::arrayOf('string'));
assertType(ArrayType::class, Expect::listOf('string'));
assertType(ArrayType::class, Expect::listOf('string', wrap: true));
assertType(EnumType::class, Expect::enum(Suit::class));
assertType(TupleType::class, Expect::tuple([Expect::string(), Expect::int()]));

// Structure creation
assertType(Structure::class, Expect::structure(['a' => Expect::string()]));
assertType(Structure::class, Expect::from(new stdClass));

// AnyOf creation
assertType(AnyOf::class, Expect::anyOf('a', 'b'));

// Expect::array() conditional return (via PHPStan extension)
assertType(Structure::class, Expect::array(['a' => Expect::string()]));
assertType(ArrayType::class, Expect::array());
assertType(ArrayType::class, Expect::array(['a', 'b']));
assertType(ArrayType::class, Expect::array(null));

// Fluent methods preserve concrete type
assertType(StringType::class, Expect::string()->required());
assertType(StringType::class, Expect::string()->nullable());
assertType(StringType::class, Expect::string()->default('foo'));
assertType(StringType::class, Expect::string()->description('foo'));
assertType(StringType::class, Expect::string()->castTo('int'));
assertType(NumberType::class, Expect::int()->min(0)->max(100));
assertType(Type::class, Expect::bool()->mergeWith(fn($value, $base) => $value || $base));
assertType(ArrayType::class, Expect::arrayOf('string')->mergeMode(MergeMode::Replace));
assertType(ArrayType::class, Expect::array()->items('string'));
assertType(EnumType::class, Expect::enum(Suit::class)->nullable());
assertType(TupleType::class, Expect::tuple([Expect::string()])->otherItems('int'));
assertType(TupleType::class, Expect::tuple([Expect::string()])->extend([Expect::int()]));

assertType(Structure::class, Expect::structure([])->required());
assertType(Structure::class, Expect::structure([])->otherItems('string'));
assertType(Structure::class, Expect::structure([])->skipDefaults());
assertType(Structure::class, Expect::structure([])->min(1)->max(5));
assertType(Structure::class, Expect::structure([])->mergeMode(MergeMode::AppendKeys));
assertType(Structure::class, Expect::structure([])->description('foo'));

assertType(AnyOf::class, Expect::anyOf('a', 'b')->required());
assertType(AnyOf::class, Expect::anyOf('a', 'b')->firstIsDefault());
assertType(AnyOf::class, Expect::anyOf('a', 'b')->mergeWith(fn($value, $base) => $value));

// Structure::extend() returns Structure
assertType(Structure::class, Expect::structure([])->extend(['b' => Expect::int()]));
