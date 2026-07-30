<?php declare(strict_types=1);

use Nette\Schema\Elements;
use Nette\Schema\Expect;
use Nette\Schema\Kind;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('scalar types', function () {
	Assert::same(
		['kind' => Kind::String, 'min' => null, 'max' => null, 'pattern' => null, 'format' => null, 'nullable' => false, 'dynamic' => false, 'required' => false, 'description' => null],
		Expect::string()->describe(),
	);
	Assert::same(Kind::Int, Expect::int()->describe()['kind']);
	Assert::same(Kind::Float, Expect::float()->describe()['kind']);
	Assert::same(Kind::Bool, Expect::bool()->describe()['kind']);
	Assert::same(Kind::Null, Expect::null()->describe()['kind']);
	Assert::same(Kind::Any, Expect::mixed()->describe()['kind']);
});


test('common metadata', function () {
	$d = Expect::int()->required()->description('Count')->describe();
	Assert::true($d['required']);
	Assert::same('Count', $d['description']);
});


test('nullable and dynamic are flags, not variants', function () {
	$d = Expect::int()->nullable()->describe();
	Assert::same(Kind::Int, $d['kind']);
	Assert::true($d['nullable']);

	$d = Expect::type('null|int|string')->describe();
	Assert::same(Kind::Union, $d['kind']);
	Assert::true($d['nullable']);
	Assert::same([Kind::Int, Kind::String], array_map(fn($v) => $v['kind'], $d['variants']));

	Assert::true(Expect::int()->dynamic()->describe()['dynamic']);
});


test('min(), max() and pattern() narrow the type, a range in the expression too', function () {
	$d = Expect::int()->min(1)->max(5)->describe();
	Assert::same(1.0, $d['min']);
	Assert::same(5.0, $d['max']);

	$d = Expect::type('int:1..10')->min(3)->max(20)->describe();
	Assert::same(3.0, $d['min']);
	Assert::same(10.0, $d['max']);

	$d = Expect::type('int|string')->min(3)->pattern('\d+')->describe();
	Assert::same(3.0, $d['variants'][0]['min']);
	Assert::same(3.0, $d['variants'][1]['min']);
	Assert::null($d['variants'][0]['pattern'] ?? null);
	Assert::same('\d+', $d['variants'][1]['pattern']);

	Assert::same('x', Expect::type('pattern:y')->pattern('x')->describe()['pattern']);
	Assert::same('email', Expect::email()->describe()['format']);
});


test('collections report their children as schemas', function () {
	$d = Expect::array()->describe();
	Assert::same(Kind::Array, $d['kind']);
	Assert::null($d['items']);

	$d = Expect::arrayOf('string', 'int')->describe();
	Assert::type(Elements\Type::class, $d['items']);
	Assert::same(Kind::String, $d['items']->describe()['kind']);
	Assert::same(Kind::Int, $d['keys']->describe()['kind']);

	$d = Expect::listOf(Expect::int()->min(1))->max(3)->describe();
	Assert::same(Kind::List, $d['kind']);
	Assert::same(1.0, $d['items']->describe()['min']);
	Assert::same(3.0, $d['max']);

	$d = Expect::type('int[]')->max(3)->describe();
	Assert::same(Kind::Iterable, $d['kind']);
	Assert::same(Kind::Int, $d['items']['kind']); // from the expression, not a schema
	Assert::same(3.0, $d['max']);

	$d = Expect::type('array|string')->items('int')->describe();
	Assert::type(Elements\Type::class, $d['variants'][0]['items']);
	Assert::false(isset($d['variants'][1]['items']));
});


test('classes and legacy names', function () {
	$d = Expect::type(DateTime::class)->describe();
	Assert::same(Kind::Instance, $d['kind']);
	Assert::same(DateTime::class, $d['type']);

	$d = Expect::type('numeric')->describe();
	Assert::same(Kind::Other, $d['kind']);
	Assert::same('numeric', $d['type']);
});


test('structure', function () {
	$schema = Expect::structure([
		'name' => Expect::string()->required(),
		'age' => Expect::int(),
	])->otherItems('bool')->min(1);
	$d = $schema->describe();

	Assert::same(Kind::Structure, $d['kind']);
	Assert::true($d['required']);
	Assert::same($schema->getShape(), $d['shape']);
	Assert::true($d['shape']['name']->describe()['required']);
	Assert::same(Kind::Bool, $d['otherItems']->describe()['kind']);
	Assert::same(1, $d['min']);

	Assert::false(Expect::structure([])->required(false)->describe()['required']);
});


test('Expect::from()', function () {
	$d = Expect::from(new class {
		public string $name;
		public ?int $age = null;
	})->describe();

	Assert::same(Kind::Structure, $d['kind']);
	Assert::true($d['shape']['name']->describe()['required']);
	Assert::same(Kind::Int, $d['shape']['age']->describe()['kind']);
	Assert::true($d['shape']['age']->describe()['nullable']);
});


test('anyOf', function () {
	$d = Expect::anyOf('a', 'b', 1)->describe();
	Assert::same(Kind::Enum, $d['kind']);
	Assert::same(['a', 'b', 1], $d['values']);
	Assert::same([], $d['variants']);
	Assert::false($d['nullable']);

	$d = Expect::anyOf('a', null)->describe();
	Assert::same(['a'], $d['values']);
	Assert::true($d['nullable']);

	$d = Expect::anyOf(Expect::string(), 'a', 'b')->nullable()->dynamic()->describe();
	Assert::same(Kind::Union, $d['kind']);
	Assert::true($d['nullable']);
	Assert::same(['a', 'b'], $d['values']);
	Assert::count(1, $d['variants']); // the variant dynamic() adds is not reported
	Assert::type(Elements\Type::class, $d['variants'][0]);

	Assert::same(Kind::Null, Expect::anyOf(null)->describe()['kind']);
});
