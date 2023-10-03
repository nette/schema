<?php

/**
 * @phpversion 7.4
 */

declare(strict_types=1);

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::with(Structure::class, function () {
	$schema = Expect::from($obj = new class {
		public string $dsn = 'mysql';
		public ?string $user;
		public ?string $password = null;
		public array $options = [];
		public bool $debugger = true;
		public $mixed;
		public array $arr = [1];
	});

	Assert::type(Structure::class, $schema);
	Assert::equal([
		'dsn' => Expect::string('mysql'),
		'user' => Expect::type('?string')->required(),
		'password' => Expect::type('?string'),
		'options' => Expect::array(),
		'debugger' => Expect::bool(true),
		'mixed' => Expect::mixed(),
		'arr' => Expect::type('array')->default([1]),
	], $schema->items);
	Assert::type($obj, (new Processor)->process($schema, ['user' => '']));
});
