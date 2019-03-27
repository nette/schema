<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // without items
	$schema = Expect::structure([]);

	Assert::equal((object) [], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, ["Unexpected option '0', '1', '2'."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['key' => 'val']);
	}, ["Unexpected option 'key'."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'one');
	}, ["The option expects to be array, string 'one' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, true);
	}, ['The option expects to be array, bool given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The option expects to be array, int 123 given.']);

	Assert::equal((object) [], (new Processor)->process($schema, null));
});


test(function () { // accepts object
	$schema = Expect::structure(['a' => Expect::string()]);

	Assert::equal((object) ['a' => null], (new Processor)->process($schema, (object) []));

	Assert::equal((object) ['a' => 'foo'], (new Processor)->process($schema, (object) ['a' => 'foo']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, (object) ['a' => 1]);
	}, ["The option 'a' expects to be string, int 1 given."]);
});


test(function () { // scalar items
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
		(new Processor)->process($schema, [])
	);
});


test(function () { // array items
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
		(new Processor)->process($schema, [])
	);
});


test(function () { // default value must be readonly
	Assert::exception(function () {
		$schema = Expect::structure([])->default([]);
	}, Nette\InvalidStateException::class);
});


test(function () { // with indexed item
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
		$processor->process($schema, [])
	);

	Assert::equal(
		(object) [
			'key1' => null,
			'key2' => null,
			null,
			'arr' => [],
		],
		$processor->processMultiple($schema, [])
	);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->process($schema, [1, 2, 3]);
	}, [
		"Unexpected option '1', '2'.",
		"The option '0' expects to be string, int 1 given.",
	]);

	Assert::equal(
		(object) [
			'key1' => 'newval',
			'key2' => null,
			'newval3',
			'arr' => [],
		],
		$processor->process($schema, ['key1' => 'newval', 'newval3'])
	);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->processMultiple($schema, [['key1' => 'newval', 'newval3'], ['key2' => 'newval', 'newval4']]);
	}, ["Unexpected option '1'."]);
});


test(function () { // with indexed item & otherItems
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
		$processor->process($schema, [])
	);

	Assert::equal(
		(object) [
			'key1' => null,
			'key2' => null,
			null,
			'arr' => [],
		],
		$processor->processMultiple($schema, [])
	);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->process($schema, [1, 2, 3]);
	}, ["The option '0' expects to be string, int 1 given."]);

	Assert::equal(
		(object) [
			'key1' => 'newval',
			'key2' => null,
			'newval3',
			'arr' => [],
		],
		$processor->process($schema, ['key1' => 'newval', 'newval3'])
	);

	Assert::equal(
		(object) [
			'key1' => 'newval1',
			'key2' => 'newval2',
			'newval3',
			'arr' => [],
			'newval4',
		],
		$processor->processMultiple($schema, [['key1' => 'newval', 'newval3'], ['key1' => 'newval1', 'key2' => 'newval2', 'newval4']])
	);
});


test(function () { // item with default value
	$schema = Expect::structure([
		'b' => Expect::string(123),
	]);

	Assert::equal((object) ['b' => 123], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, ["Unexpected option '0', did you mean 'b'?"]);

	Assert::equal((object) ['b' => 123], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 123]);
	}, ["The option 'b' expects to be string, int 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => null]);
	}, ["The option 'b' expects to be string, null given."]);

	Assert::equal((object) ['b' => 'val'], (new Processor)->process($schema, ['b' => 'val']));
});


test(function () { // item without default value
	$schema = Expect::structure([
		'b' => Expect::string(),
	]);

	Assert::equal((object) ['b' => null], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 123]);
	}, ["The option 'b' expects to be string, int 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => null]);
	}, ["The option 'b' expects to be string, null given."]);

	Assert::equal((object) ['b' => 'val'], (new Processor)->process($schema, ['b' => 'val']));
});


test(function () { // required item
	$schema = Expect::structure([
		'b' => Expect::string()->required(),
		'c' => Expect::array()->required(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, [
		"The mandatory option 'b' is missing.",
		"The mandatory option 'c' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 'val']);
	}, ["The mandatory option 'c' is missing."]);

	Assert::equal(
		(object) ['b' => 'val', 'c' => [1, 2, 3]],
		(new Processor)->process($schema, ['b' => 'val', 'c' => [1, 2, 3]])
	);
});


test(function () { // other items
	$schema = Expect::structure([
		'key' => Expect::string(),
	])->otherItems(Expect::string());

	Assert::equal((object) ['key' => null], (new Processor)->process($schema, []));
	Assert::equal((object) ['key' => null, 'other' => 'foo'], (new Processor)->process($schema, ['other' => 'foo']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['other' => 123]);
	}, ["The option 'other' expects to be string, int 123 given."]);
});


test(function () { // structure items
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
	}, ["The mandatory option 'b › y' is missing."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"Unexpected option '0', did you mean 'a'?",
		"The mandatory option 'b › y' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['a' => 'val']);
	}, [
		"The option 'a' expects to be array, string 'val' given.",
		"The mandatory option 'b › y' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['a' => null]);
	}, ["The mandatory option 'b › y' is missing."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 123]);
	}, ["The option 'b' expects to be array, int 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => null]);
	}, ["The mandatory option 'b › y' is missing."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 'val']);
	}, ["The option 'b' expects to be array, string 'val' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => ['x' => 'val']]);
	}, [
		"Unexpected option 'b › x', did you mean 'y'?",
		"The mandatory option 'b › y' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => ['x1' => 'val', 'x2' => 'val']]);
	}, [
		"Unexpected option 'b › x1', 'b › x2'.",
		"The mandatory option 'b › y' is missing.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => ['y' => 123]]);
	}, ["The option 'b › y' expects to be string, int 123 given."]);

	Assert::equal(
		(object) ['a' => (object) ['x' => 'defval'], 'b' => (object) ['y' => 'val']],
		(new Processor)->process($schema, ['b' => ['y' => 'val']])
	);
});


test(function () { // processing
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
		(new Processor)->process($schema, ['b' => ['y' => 'newval']])
	);
	Assert::equal(
		(object) ['a' => (object) ['x' => 'newval'], 'b' => (object) ['y' => 'newval']],
		(new Processor)->processMultiple($schema, [['a' => ['x' => 'newval']], ['b' => ['y' => 'newval']]])
	);
	Assert::equal(
		(object) ['a' => (object) ['x' => 'defval'], 'b' => (object) ['y' => 'newval']],
		(new Processor)->processMultiple($schema, [null, ['b' => ['y' => 'newval']]])
	);
	Assert::equal(
		(object) ['a' => (object) ['x' => 'defval'], 'b' => (object) ['y' => 'newval']],
		(new Processor)->processMultiple($schema, [['b' => ['y' => 'newval']], null])
	);
});
