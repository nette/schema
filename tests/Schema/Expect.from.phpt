<?php

declare(strict_types=1);

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!class_exists(Nette\Utils\Type::class)) {
	Tester\Environment::skip('Expect::from() requires nette/utils 3.x');
}


Assert::with(Structure::class, function () {
	$schema = Expect::from(new stdClass);

	Assert::type(Structure::class, $schema);
	Assert::same([], $schema->items);
	Assert::type(stdClass::class, (new Processor)->process($schema, []));
});


Assert::with(Structure::class, function () {
	$schema = Expect::from($obj = new class {
		/** @var string */
		public $dsn = 'mysql';

		/** @var string|null */
		public $user;

		/** @var ?string */
		public $password;

		/** @var string[] */
		public $options = [1];

		/** @var bool */
		public $debugger = true;
		public $mixed;

		/** @var array|null */
		public $arr;

		/** @var string */
		public $required;
	});

	Assert::type(Structure::class, $schema);
	Assert::equal([
		'dsn' => Expect::string('mysql'),
		'user' => Expect::type('string|null'),
		'password' => Expect::type('?string'),
		'options' => Expect::type('string[]')->default([1]),
		'debugger' => Expect::bool(true),
		'mixed' => Expect::mixed(),
		'arr' => Expect::type('array|null')->default(null),
		'required' => Expect::type('string')->required(),
	], $schema->items);
	Assert::type($obj, (new Processor)->process($schema, ['required' => '']));
});


Assert::exception(function () {
	Expect::from(new class {
		/** @var Unknown */
		public $unknown;
	});
}, Nette\NotImplementedException::class, 'Anonymous classes are not supported.');


Assert::with(Structure::class, function () { // overwritten item
	$schema = Expect::from(new class {
		/** @var string */
		public $dsn = 'mysql';

		/** @var string|null */
		public $user;
	}, ['dsn' => Expect::int(123)]);

	Assert::equal([
		'dsn' => Expect::int(123),
		'user' => Expect::type('string|null'),
	], $schema->items);
});


Assert::with(Structure::class, function () { // nested object
	$obj = new class {
		/** @var object */
		public $inner;
	};
	$obj->inner = new class {
		/** @var string */
		public $name;
	};

	$schema = Expect::from($obj);

	Assert::equal([
		'inner' => Expect::structure([
			'name' => Expect::string()->required(),
		])->castTo(get_class($obj->inner)),
	], $schema->items);
});
