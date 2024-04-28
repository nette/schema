<?php

declare(strict_types=1);

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::with(Structure::class, function () {
	$schema = Expect::from(stdClass::class);

	Assert::type(Structure::class, $schema);
	Assert::same([], $schema->items);
	Assert::type(stdClass::class, (new Processor)->process($schema, []));
});


Assert::with(Structure::class, function () {
	class Data1
	{
		public string $dsn = 'mysql';
		public ?string $user;
		public ?string $password = null;
		public array|int $options = [];
		public bool $debugger = true;
		public mixed $mixed;
		public array $arr = [1];
	}

	$schema = Expect::from(Data1::class);

	Assert::type(Structure::class, $schema);
	Assert::equal([
		'dsn' => Expect::string('mysql'),
		'user' => Expect::type('?string')->required(),
		'password' => Expect::type('?string'),
		'options' => Expect::type('array|int')->default([]),
		'debugger' => Expect::bool(true),
		'mixed' => Expect::mixed()->required(),
		'arr' => Expect::type('array')->default([1]),
	], $schema->items);
	Assert::type(Data1::class, (new Processor)->process($schema, ['user' => '', 'mixed' => '']));
});


Assert::with(Structure::class, function () { // constructor injection
	class Data2
	{
		public function __construct(
			public ?string $user,
			public ?string $password = null,
		) {
		}
	}

	$schema = Expect::from(Data2::class);

	Assert::type(Structure::class, $schema);
	Assert::equal([
		'user' => Expect::type('?string')->required(),
		'password' => Expect::type('?string'),
	], $schema->items);
	Assert::equal(
		new Data2('foo', 'bar'),
		(new Processor)->process($schema, ['user' => 'foo', 'password' => 'bar']),
	);
});


Assert::with(Structure::class, function () { // overwritten item
	class Data3
	{
		public string $dsn = 'mysql';
		public ?string $user;
	}

	$schema = Expect::from(Data3::class, ['dsn' => Expect::int(123)]);

	Assert::equal([
		'dsn' => Expect::int(123),
		'user' => Expect::type('?string')->required(),
	], $schema->items);
});


Assert::with(Structure::class, function () { // nested object
	class Data4
	{
		public Data5 $inner;
	}

	class Data5
	{
		public string $name;
	}

	$schema = Expect::from(Data4::class);

	Assert::equal([
		'inner' => Expect::structure([
			'name' => Expect::string()->required(),
		])->castTo(Data5::class),
	], $schema->items);
});
