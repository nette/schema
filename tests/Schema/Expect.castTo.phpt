<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('built-in', function () {
	$schema = Expect::int()->castTo('string');
	Assert::same('10', (new Processor)->process($schema, 10));

	$schema = Expect::string()->castTo('array');
	Assert::same(['foo'], (new Processor)->process($schema, 'foo'));
});


test('simple object', function () {
	class Foo1
	{
		public $a;
		public $b;
	}

	$foo = new Foo1;
	$foo->a = 1;
	$foo->b = 2;

	$schema = Expect::array()->castTo(Foo1::class);
	Assert::equal(
		$foo,
		(new Processor)->process($schema, ['a' => 1, 'b' => 2])
	);
});


test('object with constructor', function () {
	if (PHP_VERSION_ID < 80000) {
		return;
	}

	class Foo2
	{
		private $a;
		private $b;


		public function __construct(int $a, int $b)
		{
			$this->b = $b;
			$this->a = $a;
		}
	}

	$schema = Expect::array()->castTo(Foo2::class);
	Assert::equal(
		new Foo2(1, 2),
		(new Processor)->process($schema, ['b' => 2, 'a' => 1])
	);
});


test('DateTime', function () {
	$schema = Expect::string()->castTo(DateTime::class);
	Assert::equal(
		new DateTime('2021-01-01'),
		(new Processor)->process($schema, '2021-01-01')
	);
});
