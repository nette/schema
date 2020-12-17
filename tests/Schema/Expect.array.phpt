<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('without default value', function () {
	$schema = Expect::array();

	Assert::same([], (new Processor)->process($schema, []));

	Assert::same([1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	Assert::same(['key' => 'val'], (new Processor)->process($schema, ['key' => 'val']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'one');
	}, ["The option expects to be array, 'one' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, true);
	}, ['The option expects to be array, true given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The option expects to be array, 123 given.']);

	Assert::same([], (new Processor)->process($schema, null));
});


test('not merging', function () {
	$schema = Expect::array([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
		'arr' => ['item'],
	])->mergeDefaults(false);

	Assert::same([], (new Processor)->process($schema, []));

	Assert::same(
		[1, 2, 3],
		(new Processor)->process($schema, [1, 2, 3])
	);
});


test('merging', function () {
	$schema = Expect::array([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
		'arr' => ['item'],
	]);

	Assert::same([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
		'arr' => ['item'],
	], (new Processor)->process($schema, []));

	Assert::same(
		[
			'key1' => 'val1',
			'key2' => 'val2',
			'val3',
			'arr' => ['item'],
			1, 2, 3,
		],
		(new Processor)->process($schema, [1, 2, 3])
	);

	Assert::same(
		[
			'key1' => 'newval',
			'key2' => 'val2',
			'val3',
			'arr' => ['item', 'newitem'],
			'key3' => 'newval',
			'newval3',
		],
		(new Processor)->process($schema, [
			'key1' => 'newval',
			'key3' => 'newval',
			'newval3', 'arr' => ['newitem'],
		])
	);
});


test('merging & other items validation', function () {
	$schema = Expect::array([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
	])->items('string');

	Assert::same([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
	], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"The option '0' expects to be string, 1 given.",
		"The option '1' expects to be string, 2 given.",
		"The option '2' expects to be string, 3 given.",
	]);

	Assert::same(
		[
			'key1' => 'newval',
			'key2' => 'val2',
			'val3',
			'key3' => 'newval',
			'newval3',
		],
		(new Processor)->process($schema, [
			'key1' => 'newval',
			'key3' => 'newval',
			'newval3',
		])
	);
});


test('merging & other items validation', function () {
	$schema = Expect::array()->items('string');

	Assert::same([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
	], $schema->merge([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
	], null));

	Assert::same(
		[
			'key1' => 'newval',
			'key2' => 'val2',
			'val3',
			'key3' => 'newval',
			'newval3',
		],
		$schema->merge([
			'key1' => 'newval',
			'key3' => 'newval',
			'newval3',
		], [
			'key1' => 'val1',
			'key2' => 'val2',
			'val3',
		])
	);
});


test('items() & scalar', function () {
	$schema = Expect::array([
		'a' => 'defval',
	])->items('string');

	Assert::same(['a' => 'defval'], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"The option '0' expects to be string, 1 given.",
		"The option '1' expects to be string, 2 given.",
		"The option '2' expects to be string, 3 given.",
	]);

	Assert::same(['a' => 'val'], (new Processor)->process($schema, ['a' => 'val']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['a' => null]);
	}, ["The option 'a' expects to be string, null given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 123]);
	}, ["The option 'b' expects to be string, 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => null]);
	}, ["The option 'b' expects to be string, null given."]);

	Assert::same(['a' => 'defval', 'b' => 'val'], (new Processor)->process($schema, ['b' => 'val']));
});


test('items() & structure', function () {
	$schema = Expect::array([
		'a' => 'defval',
	])->items(Expect::structure(['k' => Expect::string()]));

	Assert::same(['a' => 'defval'], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['a' => 'val']);
	}, ["The option 'a' expects to be array, 'val' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"The option '0' expects to be array, 1 given.",
		"The option '1' expects to be array, 2 given.",
		"The option '2' expects to be array, 3 given.",
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => 'val']);
	}, ["The option 'b' expects to be array, 'val' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['b' => ['a' => 'val']]);
	}, ["Unexpected option 'b › a', did you mean 'k'?"]);

	Assert::equal(
		['a' => 'defval', 'b' => (object) ['k' => 'val']],
		(new Processor)->process($schema, ['b' => ['k' => 'val']])
	);
});


test('arrayOf() & scalar', function () {
	$schema = Expect::arrayOf('string|int');

	Assert::same([], (new Processor)->process($schema, []));

	Assert::same([1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	Assert::same([10 => 20], (new Processor)->process($schema, [10 => 20]));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, false]);
	}, ["The option '2' expects to be string or int, false given."]);

	Assert::same(['key' => 'val'], (new Processor)->process($schema, ['key' => 'val']));
});


test('arrayOf() error', function () {
	Assert::exception(function () {
		Expect::arrayOf(['a' => Expect::string()]);
	}, TypeError::class);
});


test('type[]', function () {
	$schema = Expect::type('int[]');

	Assert::same([], (new Processor)->process($schema, null));

	Assert::same([], (new Processor)->process($schema, []));

	Assert::same([1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, false]);
	}, ['The option expects to be int[], array given.']);

	Assert::same(['key' => 1], (new Processor)->process($schema, ['key' => 1]));
});
