<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('without items', function () {
	$schema = Expect::structure([]);

	Assert::equal((object) [], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, ["Unexpected item '0'.", "Unexpected item '1'.", "Unexpected item '2'."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['key' => 'val']);
	}, ["Unexpected item 'key'."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'one');
	}, ["The item expects to be array, 'one' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, true);
	}, ['The item expects to be array, true given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The item expects to be array, 123 given.']);

	Assert::equal((object) [], (new Processor)->process($schema, null));
});


test('accepts object', function () {
	$schema = Expect::structure(['a' => Expect::string()]);

	Assert::equal((object) ['a' => null], (new Processor)->process($schema, (object) []));

	Assert::equal((object) ['a' => 'foo'], (new Processor)->process($schema, (object) ['a' => 'foo']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, (object) ['a' => 1]);
	}, ["The item 'a' expects to be string, 1 given."]);

	$schema = Expect::structure(['a' => Expect::string()->before('strrev')]);

	Assert::equal((object) ['a' => 'oof'], (new Processor)->process($schema, (object) ['a' => 'foo']));

	Assert::equal(
		(object) ['a' => 'rab'],
		(new Processor)->processMultiple($schema, [(object) ['a' => 'foo'], (object) ['a' => 'bar']]),
	);
});


test('scalar items', function () {
	$schema = Expect::structure([
		'a' => Expect::string(),
		'b' => Expect::int(),
		'c' => Expect::bool(),
		'd' => Expect::scalar(),
		'e' => Expect::type('string'),
		'f' => Expect::type('int'),
		'g' => Expect::string('abc'),
		'h' => Expect::string(123),
		'i' => Expect::type('string')->default(123),
		'j' => Expect::anyOf(1, 2),
	]);

	Assert::equal(
		(object) ['a' => null, 'b' => null, 'c' => null, 'd' => null, 'e' => null, 'f' => null, 'g' => 'abc', 'h' => 123, 'i' => 123, 'j' => null],
		(new Processor)->process($schema, []),
	);
});


test('array items', function () {
	$schema = Expect::structure([
		'a' => Expect::array(),
		'b' => Expect::array([]),
		'c' => Expect::arrayOf('string'),
		'd' => Expect::list(),
		'e' => Expect::listOf('string'),
		'f' => Expect::type('array'),
		'g' => Expect::type('list'),
		'h' => Expect::structure([]),
	]);

	Assert::equal(
		(object) ['a' => [], 'b' => [], 'c' => [], 'd' => [], 'e' => [], 'f' => [], 'g' => [], 'h' => (object) []],
		(new Processor)->process($schema, []),
	);
});


testException(
	'default value must be readonly',
	fn() => Expect::structure([])->default([]),
	Nette\InvalidStateException::class,
);


test('with indexed item', function () {
	$schema = Expect::structure([
		'key1' => Expect::string(),
		'key2' => Expect::string(),
		Expect::string(),
		'arr' => Expect::arrayOf('string'),
	]);

	$processor = new Processor;

	Assert::equal(
		(object) [
			'key1' => null,
			'key2' => null,
			null,
			'arr' => [],
		],
		$processor->process($schema, []),
	);

	Assert::equal(
		(object) [
			'key1' => null,
			'key2' => null,
			null,
			'arr' => [],
		],
		$processor->processMultiple($schema, []),
	);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->process($schema, [1, 2, 3]);
	}, [
		"Unexpected item '1'.",
		"Unexpected item '2'.",
		"The item '0' expects to be string, 1 given.",
	]);

	Assert::equal(
		(object) [
			'key1' => 'newval',
			'key2' => null,
			'newval3',
			'arr' => [],
		],
		$processor->process($schema, ['key1' => 'newval', 'newval3']),
	);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->processMultiple($schema, [['key1' => 'newval', 'newval3'], ['key2' => 'newval', 'newval4']]);
	}, ["Unexpected item '1'."]);
});


test('with indexed item & otherItems', function () {
	$schema = Expect::structure([
		'key1' => Expect::string(),
		'key2' => Expect::string(),
		Expect::string(),
		'arr' => Expect::arrayOf('string'),
	])->otherItems('scalar');

	$processor = new Processor;

	Assert::equal(
		(object) [
			'key1' => null,
			'key2' => null,
			null,
			'arr' => [],
		],
		$processor->process($schema, []),
	);

	Assert::equal(
		(object) [
			'key1' => null,
			'key2' => null,
			null,
			'arr' => [],
		],
		$processor->processMultiple($schema, []),
	);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->process($schema, [1, 2, 3]);
	}, ["The item '0' expects to be string, 1 given."]);

	Assert::equal(
		(object) [
			'key1' => 'newval',
			'key2' => null,
			'newval3',
			'arr' => [],
		],
		$processor->process($schema, ['key1' => 'newval', 'newval3']),
	);

	Assert::equal(
		(object) [
			'key1' => 'newval1',
			'key2' => 'newval2',
			'newval3',
			'arr' => [],
			'newval4',
		],
		$processor->processMultiple($schema, [['key1' => 'newval', 'newval3'], ['key1' => 'newval1', 'key2' => 'newval2', 'newval4']]),
	);
});


test('item with default value', function () {
	$schema = Expect::structure([
		'b' => Expect::string(123),
	]);

	Assert::equal((object) ['b' => 123], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"Unexpected item '0', did you mean 'b'?",
		"Unexpected item '1', did you mean 'b'?",
		"Unexpected item '2', did you mean 'b'?",
	]);

	Assert::equal((object) ['b' => 123], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 123]);
	}, ["The item 'b' expects to be string, 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => null]);
	}, ["The item 'b' expects to be string, null given."]);

	Assert::equal((object) ['b' => 'val'], (new Processor)->process($schema, ['b' => 'val']));
});


test('item without default value', function () {
	$schema = Expect::structure([
		'b' => Expect::string(),
	]);

	Assert::equal((object) ['b' => null], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 123]);
	}, ["The item 'b' expects to be string, 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => null]);
	}, ["The item 'b' expects to be string, null given."]);

	Assert::equal((object) ['b' => 'val'], (new Processor)->process($schema, ['b' => 'val']));
});


test('required item', function () {
	$schema = Expect::structure([
		'b' => Expect::string()->required(),
		'c' => Expect::array()->required(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, [
		"The mandatory item 'b' is missing.",
		"The mandatory item 'c' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 'val']);
	}, ["The mandatory item 'c' is missing."]);

	Assert::equal(
		(object) ['b' => 'val', 'c' => [1, 2, 3]],
		(new Processor)->process($schema, ['b' => 'val', 'c' => [1, 2, 3]]),
	);
});


test('other items', function () {
	$schema = Expect::structure([
		'key' => Expect::string(),
	])->otherItems(Expect::string());

	Assert::equal((object) ['key' => null], (new Processor)->process($schema, []));
	Assert::equal((object) ['key' => null, 'other' => 'foo'], (new Processor)->process($schema, ['other' => 'foo']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['other' => 123]);
	}, ["The item 'other' expects to be string, 123 given."]);
});


test('structure items', function () {
	$schema = Expect::structure([
		'a' => Expect::structure([
			'x' => Expect::string('defval'),
		]),
		'b' => Expect::structure([
			'y' => Expect::string()->required(),
		]),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ["The mandatory item 'b\u{a0}›\u{a0}y' is missing."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"Unexpected item '0', did you mean 'a'?",
		"Unexpected item '1', did you mean 'a'?",
		"Unexpected item '2', did you mean 'a'?",
		"The mandatory item 'b\u{a0}›\u{a0}y' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['a' => 'val']);
	}, [
		"The item 'a' expects to be array, 'val' given.",
		"The mandatory item 'b\u{a0}›\u{a0}y' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['a' => null]);
	}, ["The mandatory item 'b\u{a0}›\u{a0}y' is missing."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 123]);
	}, ["The item 'b' expects to be array, 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => null]);
	}, ["The mandatory item 'b\u{a0}›\u{a0}y' is missing."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 'val']);
	}, ["The item 'b' expects to be array, 'val' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => ['x' => 'val']]);
	}, [
		"Unexpected item 'b\u{a0}›\u{a0}x', did you mean 'y'?",
		"The mandatory item 'b\u{a0}›\u{a0}y' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => ['x1' => 'val', 'x2' => 'val']]);
	}, [
		"Unexpected item 'b\u{a0}›\u{a0}x1'.",
		"Unexpected item 'b\u{a0}›\u{a0}x2'.",
		"The mandatory item 'b\u{a0}›\u{a0}y' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => ['y' => 123]]);
	}, ["The item 'b\u{a0}›\u{a0}y' expects to be string, 123 given."]);

	Assert::equal(
		(object) ['a' => (object) ['x' => 'defval'], 'b' => (object) ['y' => 'val']],
		(new Processor)->process($schema, ['b' => ['y' => 'val']]),
	);
});


test('processing', function () {
	$schema = Expect::structure([
		'a' => Expect::structure([
			'x' => Expect::string('defval'),
		]),
		'b' => Expect::structure([
			'y' => Expect::string()->required(),
		]),
	]);

	Assert::equal(
		(object) ['a' => (object) ['x' => 'defval'], 'b' => (object) ['y' => 'newval']],
		(new Processor)->process($schema, ['b' => ['y' => 'newval']]),
	);
	Assert::equal(
		(object) ['a' => (object) ['x' => 'newval'], 'b' => (object) ['y' => 'newval']],
		(new Processor)->processMultiple($schema, [['a' => ['x' => 'newval']], ['b' => ['y' => 'newval']]]),
	);
	Assert::equal(
		(object) ['a' => (object) ['x' => 'defval'], 'b' => (object) ['y' => 'newval']],
		(new Processor)->processMultiple($schema, [null, ['b' => ['y' => 'newval']]]),
	);
	Assert::equal(
		(object) ['a' => (object) ['x' => 'defval'], 'b' => (object) ['y' => 'newval']],
		(new Processor)->processMultiple($schema, [['b' => ['y' => 'newval']], null]),
	);
});


test('processing without default values', function () {
	$schema = Expect::structure([
		'a' => Expect::string(), // implicit default
		'b' => Expect::string('hello'), // explicit default
		'c' => Expect::string()->nullable(),
		'd' => Expect::string()->required(),
	]);

	$processor = new Processor;
	$processor->skipDefaults();

	checkValidationErrors(function () use ($schema, $processor) {
		$processor->process($schema, []);
	}, ["The mandatory item 'd' is missing."]);

	Assert::equal(
		(object) ['d' => 'newval'],
		$processor->process($schema, ['d' => 'newval']),
	);
});


test('optional structure', function () {
	$schema = Expect::structure([
		'req' => Expect::string()->required(),
		'optional' => Expect::structure([
			'req' => Expect::string()->required(),
			'foo' => Expect::string(),
		])->required(false),
	]);

	$processor = new Processor;

	Assert::equal(
		(object) [
			'req' => 'hello',
			'optional' => null,
		],
		$processor->process($schema, ['req' => 'hello']),
	);

	checkValidationErrors(function () use ($schema, $processor) {
		$processor->process($schema, ['req' => 'hello', 'optional' => ['foo' => 'Foo']]);
	}, ["The mandatory item 'optional\u{a0}›\u{a0}req' is missing."]);
});


test('deprecated item', function () {
	$schema = Expect::structure([
		'b' => Expect::string()->deprecated('depr %path%'),
	]);

	$processor = new Processor;
	Assert::equal(
		(object) ['b' => 'val'],
		$processor->process($schema, ['b' => 'val']),
	);
	Assert::same(["depr 'b'"], $processor->getWarnings());
});


test('deprecated other items', function () {
	$schema = Expect::structure([
		'key' => Expect::string(),
	])->otherItems(Expect::string()->deprecated());

	$processor = new Processor;
	Assert::equal((object) ['key' => null], $processor->process($schema, []));
	Assert::same([], $processor->getWarnings());

	Assert::equal((object) ['key' => null, 'other' => 'foo'], $processor->process($schema, ['other' => 'foo']));
	Assert::same(["The item 'other' is deprecated."], $processor->getWarnings());
});


test('processing without default values skipped on structure', function () {
	$schema = Expect::structure([
		'foo1' => Expect::structure([
			'bar' => Expect::string()->default('baz'),
		])->skipDefaults()->castTo('array'),
		'foo2' => Expect::structure([
			'bar' => Expect::string()->default('baz'),
		])->castTo('array'),
	])->castTo('array');

	$processor = new Processor;

	Assert::equal(
		[
			'foo1' => [],
			'foo2' => ['bar' => 'baz'],
		],
		$processor->process($schema, []),
	);
});


test('extend', function () {
	$schema = Expect::structure(['a' => Expect::string(), 'b' => Expect::string()]);

	Assert::equal(
		Expect::structure(['a' => Expect::int(), 'b' => Expect::string(), 'c' => Expect::int()]),
		$schema->extend(['a' => Expect::int(), 'c' => Expect::int()]),
	);

	Assert::equal(
		Expect::structure(['a' => Expect::int(), 'b' => Expect::string(), 'c' => Expect::int()]),
		$schema->extend(Expect::structure(['a' => Expect::int(), 'c' => Expect::int()])),
	);
});
