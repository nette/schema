<?php declare(strict_types=1);

use Nette\Schema\Elements\EnumType;
use Nette\Schema\Expect;
use Nette\Schema\JsonSchema;
use Nette\Schema\Kind;
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


test('backed enum accepts a case or its backing value and yields the case', function () {
	$schema = Expect::enum(Color::class);
	Assert::type(EnumType::class, $schema);

	Assert::same(Color::Red, (new Processor)->process($schema, Color::Red));
	Assert::same(Color::Red, (new Processor)->process($schema, 'red'));
	Assert::same(Level::High, (new Processor)->process(Expect::enum(Level::class), 2));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'green');
	}, ["The item expects to be Color, 'green' given."]);

	checkValidationErrors(function () {
		(new Processor)->process(Expect::enum(Level::class), 'red');
	}, ["The item expects to be Level, 'red' given."]);

	Assert::null((new Processor)->process(Expect::enum(Color::class)->nullable(), null));
});


test('an enum class in an expression means instanceof, as for any class', function () {
	Assert::same(Nette\Schema\Elements\Type::class, Expect::type(Color::class)::class);
	Assert::same(Color::Red, (new Processor)->process(Expect::type(Color::class), Color::Red));
	checkValidationErrors(function () {
		(new Processor)->process(Expect::type(Color::class), 'red');
	}, ["The item expects to be Color, 'red' given."]);
});


test('an enum-typed property of from() maps to an EnumType', function () {
	$schema = Expect::from(new class {
		public Color $color = Color::Red;
	});
	Assert::type(EnumType::class, $schema->getShape()['color']);
	Assert::same(Color::Blue, (new Processor)->process($schema, ['color' => 'blue'])->color);

	$schema = Expect::from(new class {
		public ?Color $color = null;
	});
	Assert::type(EnumType::class, $schema->getShape()['color']);
	Assert::null((new Processor)->process($schema, [])->color);

	$schema = Expect::from(new class {
		public Suit $suit = Suit::Clubs; // a unit enum property means instanceof
	});
	Assert::same(Nette\Schema\Elements\Type::class, $schema->getShape()['suit']::class);
	Assert::same(Suit::Hearts, (new Processor)->process($schema, ['suit' => Suit::Hearts])->suit);
});


test('describe() and JSON Schema', function () {
	$d = Expect::enum(Color::class)->describe();
	Assert::same(Kind::Enum, $d['kind']);
	Assert::same(['red', 'blue'], $d['values']);
	Assert::false($d['nullable']);

	Assert::same(['type' => 'string', 'enum' => ['red', 'blue']], JsonSchema::export(Expect::enum(Color::class)));
	Assert::same(['type' => ['integer', 'null'], 'enum' => [1, 2, null]], JsonSchema::export(Expect::enum(Level::class)->nullable()));
});


testException(
	'only backed enums',
	fn() => Expect::enum(Suit::class),
	Nette\InvalidArgumentException::class,
	"'Suit' is not a backed enum.",
);
