<?php declare(strict_types=1);

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
		public mixed $a;
		public mixed $b;
	}

	$foo = new Foo1;
	$foo->a = 1;
	$foo->b = 2;

	$schema = Expect::array()->castTo(Foo1::class);
	Assert::equal(
		$foo,
		(new Processor)->process($schema, ['a' => 1, 'b' => 2]),
	);
});


test('object with constructor', function () {
	class Foo2
	{
		private int $a;
		private int $b;


		public function __construct(int $a, int $b)
		{
			$this->b = $b;
			$this->a = $a;
		}
	}

	$schema = Expect::array()->castTo(Foo2::class);
	Assert::equal(
		new Foo2(1, 2),
		(new Processor)->process($schema, ['b' => 2, 'a' => 1]),
	);
});


test('constructor mismatch reports target class', function () {
	$schema = Expect::array()->castTo(Foo2::class);

	Assert::exception(
		fn() => (new Processor)->process($schema, ['c' => 1]),
		Nette\InvalidStateException::class,
		'Unable to cast value to Foo2: %a%',
	);
});


test('property mismatch reports target class', function () {
	class Foo3
	{
		public int $a;
	}

	$schema = Expect::array()->castTo(Foo3::class);

	Assert::exception(
		fn() => (new Processor)->process($schema, ['a' => 'text']),
		Nette\InvalidStateException::class,
		'Unable to cast value to Foo3: %a%',
	);
});


test('DateTime', function () {
	$schema = Expect::string()->castTo(DateTime::class);
	Assert::equal(
		new DateTime('2021-01-01'),
		(new Processor)->process($schema, '2021-01-01'),
	);
});


enum CastSuit: string
{
	case Clubs = 'clubs';
	case Hearts = 'hearts';
}

enum CastLevel: int
{
	case Low = 1;
	case High = 2;
}

enum CastPure
{
	case One;
}

test('backed enum', function () {
	$schema = Expect::string()->castTo(CastSuit::class);
	Assert::same(CastSuit::Clubs, (new Processor)->process($schema, 'clubs'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'diamonds');
	}, ["The item expects to be 'clubs'|'hearts', 'diamonds' given."]);

	$schema = Expect::int()->castTo(CastLevel::class);
	Assert::same(CastLevel::High, (new Processor)->process($schema, 2));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 3);
	}, ['The item expects to be 1|2, 3 given.']);
});


testException(
	'pure enum cannot be a cast target',
	fn() => Expect::string()->castTo(CastPure::class),
	Nette\InvalidStateException::class,
	'Cannot cast value to pure enum CastPure.',
);
