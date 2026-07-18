<?php declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


enum Suit
{
	case Clubs;
	case Diamonds;
	case Hearts;
	case Spades;
}

test('unit enum as standalone type', function () {
	$schema = Expect::type(Suit::class);

	Assert::same(Suit::Clubs, (new Processor)->process($schema, Suit::Clubs));
	Assert::same(Suit::Hearts, (new Processor)->process($schema, Suit::Hearts));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'Clubs');
	}, ['The item expects to be Suit, \'Clubs\' given.']);
});


enum Color: string
{
	case Red = 'red';
	case Blue = 'blue';
}

enum Level: int
{
	case Low = 1;
	case High = 2;
}


test('backing value is cast to a case', function () {
	Assert::same(Color::Red, (new Processor)->process(Expect::enum(Color::class), 'red'));
	Assert::same(Level::High, (new Processor)->process(Expect::enum(Level::class), 2));
});


test('case instance passes through', function () {
	Assert::same(Color::Blue, (new Processor)->process(Expect::enum(Color::class), Color::Blue));
});


test('invalid value lists the allowed ones', function () {
	checkValidationErrors(function () {
		(new Processor)->process(Expect::enum(Color::class), 'green');
	}, ["The item expects to be 'red'|'blue', 'green' given."]);

	checkValidationErrors(function () {
		(new Processor)->process(Expect::enum(Level::class), 3);
	}, ['The item expects to be 1|2, 3 given.']);
});


test('wrong type is rejected before casting', function () {
	checkValidationErrors(function () {
		(new Processor)->process(Expect::enum(Color::class), []);
	}, ['The item expects to be string or Color, array given.']);
});


test('default and nullable', function () {
	$schema = Expect::structure([
		'color' => Expect::enum(Color::class)->default(Color::Red),
		'level' => Expect::enum(Level::class)->nullable(),
	]);

	Assert::equal(
		(object) ['color' => Color::Red, 'level' => null],
		(new Processor)->process($schema, []),
	);

	Assert::equal(
		(object) ['color' => Color::Blue, 'level' => null],
		(new Processor)->process($schema, ['color' => 'blue', 'level' => null]),
	);
});


testException(
	'pure enum is rejected',
	fn() => Expect::enum(Suit::class),
	Nette\InvalidArgumentException::class,
	"Class 'Suit' is not a backed enum.",
);


testException(
	'ordinary class is rejected',
	fn() => Expect::enum(stdClass::class),
	Nette\InvalidArgumentException::class,
	"Class 'stdClass' is not a backed enum.",
);
