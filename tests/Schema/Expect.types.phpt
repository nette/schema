<?php declare(strict_types=1);

use Nette\Schema\Elements\ArrayType;
use Nette\Schema\Elements\NumberType;
use Nette\Schema\Elements\StringType;
use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('an expression of a single kind gets its dedicated subclass of Type', function () {
	Assert::type(StringType::class, Expect::string());
	Assert::type(StringType::class, Expect::unicode());
	Assert::type(StringType::class, Expect::email());
	Assert::type(StringType::class, Expect::type('?string'));
	Assert::type(StringType::class, Expect::type('url'));
	Assert::type(StringType::class, Expect::type('pattern:\d+'));

	Assert::type(NumberType::class, Expect::int());
	Assert::type(NumberType::class, Expect::float());
	Assert::type(NumberType::class, Expect::type('number'));
	Assert::type(NumberType::class, Expect::type('int:1..5'));
	Assert::type(NumberType::class, Expect::type('int|float'));
	Assert::type(NumberType::class, Expect::type('int|null'));
	Assert::type(NumberType::class, Expect::type('float|int|null'));
	Assert::type(StringType::class, Expect::type('email|url'));

	Assert::type(ArrayType::class, Expect::array());
	Assert::type(ArrayType::class, Expect::list());
	Assert::type(ArrayType::class, Expect::arrayOf('string'));
	Assert::type(ArrayType::class, Expect::listOf('string'));
	Assert::type(ArrayType::class, Expect::type('int[]'));
	Assert::type(ArrayType::class, Expect::type('iterable'));

	Assert::same(Type::class, Expect::bool()::class);
	Assert::same(Type::class, Expect::null()::class);
	Assert::same(Type::class, Expect::mixed()::class);
	Assert::same(Type::class, Expect::scalar()::class);
	Assert::same(Type::class, Expect::type('int|string')::class);
	Assert::same(Type::class, Expect::type(DateTime::class)::class);
	Assert::same(Type::class, Expect::type('numeric')::class);

	Assert::type(StringType::class, Expect::arrayOf('string')->describe()['items']);
	Assert::type(StringType::class, Expect::from(new class {
		public string $name;
	})->getShape()['name']);
});


test('the subclasses validate exactly as Type does', function () {
	Assert::same('a@b.cz', (new Processor)->process(Expect::type('email'), 'a@b.cz'));
	Assert::same('https://nette.org', (new Processor)->process(Expect::type('url'), 'https://nette.org'));
	checkValidationErrors(function () {
		(new Processor)->process(Expect::type('url'), 'nette');
	}, ["The item expects to be url, 'nette' given."]);

	Assert::same(5, (new Processor)->process(Expect::int()->min(1), 5));
	Assert::same([1, 2], (new Processor)->process(Expect::listOf('int'), [1, 2]));
});


test('methods that make no sense for the kind are deprecated', function () {
	Assert::error(
		fn() => Expect::int()->pattern('\d+'),
		E_USER_DEPRECATED,
		'Nette\Schema\Elements\NumberType::pattern() is deprecated, a number has no pattern.',
	);
	Assert::error(
		fn() => Expect::int()->items('string'),
		E_USER_DEPRECATED,
		'Nette\Schema\Elements\NumberType::items() is deprecated, a number has no items.',
	);
	Assert::error(
		fn() => Expect::string()->items('string'),
		E_USER_DEPRECATED,
		'Nette\Schema\Elements\StringType::items() is deprecated, a string has no items.',
	);
	Assert::error(
		fn() => Expect::list()->pattern('\d+'),
		E_USER_DEPRECATED,
		'Nette\Schema\Elements\ArrayType::pattern() is deprecated, an array has no pattern.',
	);
	Assert::error(
		fn() => Expect::int()->mergeDefaults(),
		E_USER_DEPRECATED,
		'Nette\Schema\Elements\NumberType::mergeDefaults() is deprecated, a number has no default merging.',
	);
	Assert::error(
		fn() => Expect::string()->mergeDefaults(),
		E_USER_DEPRECATED,
		'Nette\Schema\Elements\StringType::mergeDefaults() is deprecated, a string has no default merging.',
	);
	Assert::noError(fn() => Expect::array()->mergeDefaults());

	Assert::error(
		fn() => Expect::type('int|string')->pattern('\d+'),
		E_USER_DEPRECATED,
		"pattern() on the union 'int|string' is deprecated, give it to the variants of anyOf() instead.",
	);
	Assert::error(fn() => Expect::type('array|string')->items('int'), E_USER_DEPRECATED);
	Assert::error(fn() => Expect::type('int|string')->mergeDefaults(), E_USER_DEPRECATED);
	Assert::noError(fn() => Expect::type('email|url')->min(3)); // one kind of value, a StringType
	Assert::noError(fn() => Expect::type('int|string')->required()->nullable()); // no option of the kind
});
