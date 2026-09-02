<?php declare(strict_types=1);

use Nette\Schema\Elements\AnyOf;
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
	Assert::type(NumberType::class, @Expect::type('int:1..5')); // range in expression is deprecated
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
	Assert::type(AnyOf::class, Expect::scalar());
	Assert::type(AnyOf::class, Expect::type('int|string'));
	Assert::same(Type::class, Expect::type(DateTime::class)::class);
	Assert::same(Type::class, @Expect::type('numeric')::class); // 'numeric' as a type is deprecated

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


test('callable is checked by syntax, the target need not exist yet', function () {
	$schema = Expect::type('callable');
	$processor = new Processor;

	Assert::same('strlen', $processor->process($schema, 'strlen'));
	Assert::same('Undefined\Klass', $processor->process($schema, 'Undefined\Klass'));
	Assert::same('Undefined\Klass::method', $processor->process($schema, 'Undefined\Klass::method'));
	Assert::same(['Undefined\Klass', 'method'], $processor->process($schema, ['Undefined\Klass', 'method']));

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->process($schema, '');
	}, ["The item expects to be callable, '' given."]);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->process($schema, 123);
	}, ['The item expects to be callable, 123 given.']);
});


test('options exist only where they mean something', function () {
	// @ mutes the notice of #[Deprecated], the exception is the point
	Assert::exception(
		fn() => @Expect::int()->pattern('\d+'),
		Nette\DeprecatedException::class,
		"pattern() is not available on 'int', only a string has it; a union takes it per variant in anyOf().",
	);
	Assert::exception(
		fn() => @Expect::string()->items('string'),
		Nette\DeprecatedException::class,
		"items() is not available on 'string', only an array has it; a union takes it per variant in anyOf().",
	);
	Assert::exception(
		fn() => @Expect::bool()->min(1),
		Nette\DeprecatedException::class,
		"min() is not available on 'bool', only a number, a string or an array has it; a union takes it per variant in anyOf().",
	);
	Assert::exception(
		fn() => @Expect::int()->mergeDefaults(),
		Nette\DeprecatedException::class,
		"mergeDefaults() is not available on 'int', only an array has it; a union takes it per variant in anyOf().",
	);
	Assert::exception(
		fn() => @(new Type('int|string'))->min(3), // a directly constructed union Type has no options either
		Nette\DeprecatedException::class,
		"min() is not available on 'int|string', only a number, a string or an array has it; a union takes it per variant in anyOf().",
	);
	Assert::exception(
		fn() => @Expect::type('int|string')->min(3), // an anyOf(), bound its variants instead; @ mutes #[Deprecated]
		Nette\DeprecatedException::class,
		'min() is not available on anyOf(), give it to the variants.',
	);
	Assert::noError(fn() => Expect::type('email|url')->min(3)); // one kind of value, a StringType
	Assert::noError(fn() => Expect::type('int|string')->required()->nullable()); // no option of the kind
});


test('Validators-coupled expressions are deprecated but still work', function () {
	Assert::error(
		fn() => Expect::type('int:1..5'),
		E_USER_DEPRECATED,
		"The range in 'int:1..5' is deprecated, use min() and max().",
	);
	Assert::error(
		fn() => Expect::type('string:..10|int'), // the union builds anyOf(), the notice comes from the variant
		E_USER_DEPRECATED,
		"The range in 'string:..10' is deprecated, use min() and max().",
	);
	Assert::error(
		fn() => Expect::type('numeric'),
		E_USER_DEPRECATED,
		"'numeric' is deprecated as a type; check the value with assert() instead.",
	);
	Assert::noError(fn() => Expect::type('pattern:\d+')); // a string format, not a range

	Assert::error(
		fn() => new Nette\Schema\Elements\Type('string'),
		E_USER_DEPRECATED,
		"'string' is a Nette\\Schema\\Elements\\StringType now, create it via Expect::type().",
	);
	Assert::noError(fn() => new Nette\Schema\Elements\Type('int|string')); // stays a plain Type
	Assert::noError(fn() => new Nette\Schema\Elements\Type(DateTime::class));

	// deprecated forms still validate exactly as before
	$processor = new Nette\Schema\Processor;
	Assert::same(3, $processor->process(@Expect::type('int:1..5'), 3));
	checkValidationErrors(function () use ($processor) {
		$processor->process(@Expect::type('int:1..5'), 9);
	}, ['The item expects to be int in range 1..5, 9 given.']);
	checkValidationErrors(function () use ($processor) {
		$processor->process(@new Type('string:2|int:1..5'), 'abc');
	}, ["The item expects to be string in range 2 or int in range 1..5, 'abc' given."]);
	Assert::same('42', $processor->process(@Expect::type('numeric'), '42'));
	checkValidationErrors(function () use ($processor) {
		$processor->process(@Expect::type('numeric'), 'abc');
	}, ["The item expects to be numeric, 'abc' given."]);
});
