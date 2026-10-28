<?php declare(strict_types=1);

use Nette\Schema\{Expect, Processor, Schema};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function menuSchema(): Schema
{
	return Expect::recursive(fn(Schema $self) => Expect::structure([
		'title' => Expect::string(),
		'items' => Expect::arrayOf($self),
	])->castTo('array'));
}


test('tree', function () {
	Assert::same(
		[
			'title' => 'Home',
			'items' => [
				'a' => ['title' => 'A', 'items' => ['a1' => ['title' => 'A1', 'items' => []]]],
				'b' => ['title' => null, 'items' => []],
			],
		],
		(new Processor)->process(menuSchema(), [
			'title' => 'Home',
			'items' => ['a' => ['title' => 'A', 'items' => ['a1' => ['title' => 'A1']]], 'b' => []],
		]),
	);
});


test('an error deep in the tree has the whole path', function () {
	checkValidationErrors(
		fn() => (new Processor)->process(menuSchema(), ['items' => ['a' => ['items' => ['x' => ['title' => 123]]]]]),
		["The item 'items\u{a0}›\u{a0}a\u{a0}›\u{a0}items\u{a0}›\u{a0}x\u{a0}›\u{a0}title' expects to be string, 123 given."],
	);
});


test('layers merge through the item schemas', function () {
	Assert::same(
		[
			'items' => [
				'a' => ['title' => 'A', 'items' => ['a1' => ['title' => 'A1', 'items' => []]]],
				'b' => ['title' => 'B', 'items' => []],
			],
			'title' => null,
		],
		(new Processor)->processMultiple(menuSchema(), [
			['items' => ['a' => ['title' => 'A']]],
			['items' => ['a' => ['items' => ['a1' => ['title' => 'A1']]], 'b' => ['title' => 'B']]],
		]),
	);
});


test('optional reference to itself', function () {
	$schema = Expect::recursive(fn(Schema $self) => Expect::structure([
		'name' => Expect::string(),
		'parent' => Expect::anyOf(null, $self),
	])->castTo('array'));

	Assert::same(
		['name' => 'x', 'parent' => ['name' => 'y', 'parent' => null]],
		(new Processor)->process($schema, ['name' => 'x', 'parent' => ['name' => 'y']]),
	);
});


test('required reference to itself cannot end', function () {
	$schema = Expect::recursive(fn(Schema $self) => Expect::structure([
		'name' => Expect::string(),
		'parent' => $self,
	]));

	Assert::exception(
		fn() => (new Processor)->process($schema, ['name' => 'x', 'parent' => ['name' => 'y']]),
		Nette\InvalidStateException::class,
		'A required item refers back to its own schema, %a%',
	);
});


test('mutual recursion', function () {
	$schema = Expect::recursive(fn(Schema $section) => Expect::structure([
		'pages' => Expect::listOf(Expect::recursive(fn(Schema $page) => Expect::structure([
			'title' => Expect::string(),
			'children' => Expect::listOf($page),
			'sections' => Expect::listOf($section),
		])->castTo('array'))),
	])->castTo('array'));

	Assert::same(
		['pages' => [[
			'title' => 'P',
			'children' => [['title' => 'C', 'children' => [], 'sections' => []]],
			'sections' => [['pages' => []]],
		]]],
		(new Processor)->process($schema, ['pages' => [['title' => 'P', 'children' => [['title' => 'C']], 'sections' => [[]]]]]),
	);
});


test('the factory must build a schema around the reference', function () {
	Assert::exception(
		fn() => Expect::recursive(fn(Schema $self) => $self),
		Nette\InvalidArgumentException::class,
		'A recursive schema cannot consist of the reference alone.',
	);
});
