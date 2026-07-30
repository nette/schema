<?php declare(strict_types=1);

use Nette\Schema\DynamicParameter;
use Nette\Schema\Kind;
use Nette\Schema\TypeExpression;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


function kinds(array $item): array
{
	return array_map(fn($v) => $v['kind'], $item['variants']);
}


test('PHP types', function () {
	Assert::same(Kind::String, TypeExpression::parse('string')['kind']);
	Assert::same(Kind::Int, TypeExpression::parse('int')['kind']);
	Assert::same(Kind::Int, TypeExpression::parse('integer')['kind']);
	Assert::same(Kind::Float, TypeExpression::parse('float')['kind']);
	Assert::same(Kind::Bool, TypeExpression::parse('bool')['kind']);
	Assert::same(Kind::Bool, TypeExpression::parse('boolean')['kind']);
	Assert::same(Kind::Null, TypeExpression::parse('null')['kind']);
	Assert::same(Kind::Any, TypeExpression::parse('mixed')['kind']);
	Assert::same(Kind::Array, TypeExpression::parse('array')['kind']);
	Assert::same(Kind::List, TypeExpression::parse('list')['kind']);
	Assert::same(Kind::Iterable, TypeExpression::parse('iterable')['kind']);
	Assert::same(Kind::Object, TypeExpression::parse('object')['kind']);
	Assert::same(Kind::Callable, TypeExpression::parse('callable')['kind']);

	Assert::same(['kind' => Kind::Bool, 'nullable' => false, 'dynamic' => false], TypeExpression::parse('bool'));
	Assert::same(
		['kind' => Kind::Int, 'min' => null, 'max' => null, 'nullable' => false, 'dynamic' => false],
		TypeExpression::parse('int'),
	);
});


test('nullable and dynamic are flags', function () {
	$d = TypeExpression::parse('?int');
	Assert::same(Kind::Int, $d['kind']);
	Assert::true($d['nullable']);

	$d = TypeExpression::parse('null|int');
	Assert::same(Kind::Int, $d['kind']);
	Assert::true($d['nullable']);

	$d = TypeExpression::parse('int|string|null');
	Assert::same(Kind::Union, $d['kind']);
	Assert::true($d['nullable']);
	Assert::same([Kind::Int, Kind::String], kinds($d));

	$d = TypeExpression::parse(DynamicParameter::class . '|int');
	Assert::same(Kind::Int, $d['kind']);
	Assert::true($d['dynamic']);

	$d = TypeExpression::parse(DynamicParameter::class);
	Assert::same(Kind::Any, $d['kind']);
	Assert::true($d['dynamic']);

	Assert::false(TypeExpression::parse('null')['nullable']);
});


test('unions and composite names', function () {
	Assert::same([Kind::Int, Kind::String], kinds(TypeExpression::parse('int|string')));
	Assert::same(Kind::Number, TypeExpression::parse('number')['kind']);
	Assert::same([Kind::Bool, Kind::Number, Kind::String], kinds(TypeExpression::parse('scalar')));
	Assert::same([Kind::Number, Kind::String], kinds(TypeExpression::parse('number|string')));
});


test('ranges from the expression', function () {
	$d = TypeExpression::parse('int:1..5');
	Assert::same(1.0, $d['min']);
	Assert::same(5.0, $d['max']);

	$d = TypeExpression::parse('string:..5');
	Assert::null($d['min']);
	Assert::same(5.0, $d['max']);

	$d = TypeExpression::parse('array:1..');
	Assert::same(1.0, $d['min']);
	Assert::null($d['max']);

	$d = TypeExpression::parse('array:1');
	Assert::same(1.0, $d['min']);
	Assert::same(1.0, $d['max']);

	$d = TypeExpression::parse('int:1..5|string:3');
	Assert::same(1.0, $d['variants'][0]['min']);
	Assert::same(3.0, $d['variants'][1]['min']);
});


test('string flavors', function () {
	$d = TypeExpression::parse('unicode:2..4');
	Assert::same(Kind::String, $d['kind']);
	Assert::same(2.0, $d['min']);

	$d = TypeExpression::parse('email');
	Assert::same(Kind::String, $d['kind']);
	Assert::same('email', $d['format']);

	$d = TypeExpression::parse('pattern:[a-z]+');
	Assert::same(Kind::String, $d['kind']);
	Assert::same('[a-z]+', $d['pattern']);
	Assert::null($d['min']);

	Assert::same('\d{1..3}', TypeExpression::parse('pattern:\d{1..3}')['pattern']);
});


test('T[] is any iterable of T, as Validators::is() sees it', function () {
	$d = TypeExpression::parse('int[]');
	Assert::same(Kind::Iterable, $d['kind']);
	Assert::same(Kind::Int, $d['items']['kind']);

	$d = TypeExpression::parse('int[][]');
	Assert::same(Kind::Iterable, $d['items']['kind']);
	Assert::same(Kind::Int, $d['items']['items']['kind']);

	Assert::same(Kind::Number, TypeExpression::parse('number[]')['items']['kind']);
	Assert::same([Kind::Iterable, Kind::String], kinds(TypeExpression::parse('int[]|string')));
});


test('an unknown name is a class name, a legacy validator is Other', function () {
	$d = TypeExpression::parse(DateTime::class);
	Assert::same(Kind::Instance, $d['kind']);
	Assert::same(DateTime::class, $d['type']);

	Assert::same(Kind::Instance, TypeExpression::parse('foo')['kind']);
	Assert::same([Kind::Instance, Kind::Instance], kinds(TypeExpression::parse('DateTime|DateTimeInterface')));

	foreach (['numeric', 'numericint', 'file', 'directory', 'url', 'uri', 'identifier', 'alnum', 'alpha', 'digit', 'lower', 'upper', 'space', 'xdigit', 'class', 'interface', 'resource', 'none'] as $name) {
		$d = TypeExpression::parse($name);
		Assert::same(Kind::Other, $d['kind'], $name);
		Assert::same($name, $d['type']);
	}

	Assert::same('numeric:1..3', TypeExpression::parse('numeric:1..3')['type']);
});


test('intersection types are Other', function () {
	$d = TypeExpression::parse('Countable&Traversable');
	Assert::same(Kind::Other, $d['kind']);
	Assert::same('Countable&Traversable', $d['type']);
});
