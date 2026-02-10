<?php

/**
 * PHPStan type tests.
 */

declare(strict_types=1);

use Nette\Schema\Elements\AnyOf;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use function PHPStan\Testing\assertType;


// Magic methods return Type
assertType(Type::class, Expect::string());
assertType(Type::class, Expect::int());
assertType(Type::class, Expect::bool());
assertType(Type::class, Expect::float());
assertType(Type::class, Expect::scalar());
assertType(Type::class, Expect::null());
assertType(Type::class, Expect::mixed());
assertType(Type::class, Expect::list());
assertType(Type::class, Expect::email());
assertType(Type::class, Expect::unicode());

// Explicit type creation
assertType(Type::class, Expect::type('string'));
assertType(Type::class, Expect::arrayOf('string'));
assertType(Type::class, Expect::listOf('string'));

// Structure creation
assertType(Structure::class, Expect::structure(['a' => Expect::string()]));
assertType(Structure::class, Expect::from(new stdClass));

// AnyOf creation
assertType(AnyOf::class, Expect::anyOf('a', 'b'));

// Expect::array() conditional return (via PHPStan extension)
assertType(Type::class, Expect::array());
assertType(Type::class, Expect::array([]));
assertType(Structure::class, Expect::array(['a' => Expect::string()]));

// Fluent methods preserve concrete type
assertType(Type::class, Expect::string()->required());
assertType(Type::class, Expect::string()->nullable());
assertType(Type::class, Expect::string()->default('foo'));
assertType(Type::class, Expect::int()->min(0)->max(100));

assertType(Structure::class, Expect::structure([])->required());
assertType(Structure::class, Expect::structure([])->otherItems('string'));
assertType(Structure::class, Expect::structure([])->skipDefaults());

assertType(AnyOf::class, Expect::anyOf('a', 'b')->required());
assertType(AnyOf::class, Expect::anyOf('a', 'b')->firstIsDefault());

// Structure::extend() returns Structure
assertType(Structure::class, Expect::structure([])->extend(['b' => Expect::int()]));
