<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$schema = Expect::int()->castTo('string');

	Assert::same('10', (new Processor)->process($schema, 10));
});


test('', function () {
	$schema = Expect::string()->castTo('array');

	Assert::same(['foo'], (new Processor)->process($schema, 'foo'));
});


test('', function () {
	$schema = Expect::array()->castTo('stdClass');

	Assert::equal((object) ['a' => 1, 'b' => 2], (new Processor)->process($schema, ['a' => 1, 'b' => 2]));
});


test('', function () {
	class TestClass
	{
		public int $a;
		public int $b;


		public function __construct()
		{
			throw new InvalidArgumentException('Class constructor is called');
		}
	}

	$schemaNoReflection = Expect::array()->castTo(TestClass::class, false);
	$schemaWithReflection = Expect::array()->castTo(TestClass::class, true);

	Assert::exception(function () use ($schemaNoReflection) {
		(new Processor)->process($schemaNoReflection, ['a' => 1, 'b' => 2]);
	}, InvalidArgumentException::class, 'Class constructor is called');

	Assert::noError(function () use ($schemaWithReflection) {
		$instance = (new Processor)->process($schemaWithReflection, ['a' => 1, 'b' => 2]);

		Assert::type(TestClass::class, $instance);
		Assert::equal(json_encode(['a' => 1, 'b' => 2]), json_encode($instance));
	});
});


test('', function () {
	class PropertyPromotionClass
	{
		public function __construct(
			public int $a,
			public int $b,
		) {
		}
	}

	$schemaNoReflection = Expect::array()->castTo(PropertyPromotionClass::class, false);
	$schemaWithReflection = Expect::array()->castTo(PropertyPromotionClass::class, true);

	Assert::exception(function () use ($schemaNoReflection) {
		(new Processor)->process($schemaNoReflection, ['a' => 1, 'b' => 2]);
	}, ArgumentCountError::class);

	Assert::noError(function () use ($schemaWithReflection) {
		$instance = (new Processor)->process($schemaWithReflection, ['a' => 1, 'b' => 2]);

		Assert::type(PropertyPromotionClass::class, $instance);
		Assert::equal(json_encode(['a' => 1, 'b' => 2]), json_encode($instance));
	});
});

// wip
class Foo
{
	public DateTime $bar;
}

$processor = new Nette\Schema\Processor;
$processor->process(
	Nette\Schema\Expect::structure([
		'bar' => Nette\Schema\Expect::string()->castTo('DateTime'),
	])->castTo(Foo::class),
	[
		'bar' => '2021-01-01',
	],
);
