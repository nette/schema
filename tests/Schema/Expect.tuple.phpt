<?php declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('without items', function () {
	$schema = Expect::tuple([]);

	Assert::equal([], (new Processor)->process($schema, []));

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

	Assert::equal([], (new Processor)->process($schema, null));
});


testException('non-indexed array', function () {
	$schema = Expect::tuple(['a' => Expect::string()]);
}, Nette\InvalidArgumentException::class, 'Tuple shape must be indexed array.');


test('accepts object', function () {
	$schema = Expect::tuple([Expect::string()]);

	Assert::equal([null], (new Processor)->process($schema, []));

	Assert::equal(['foo'], (new Processor)->process($schema, ['foo']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1]);
	}, ["The item '0' expects to be string, 1 given."]);

	$schema = Expect::tuple([Expect::string()->before('strrev')]);

	Assert::equal(['oof'], (new Processor)->process($schema, ['foo']));

	Assert::equal(
		['rab'],
		(new Processor)->processMultiple($schema, [['foo'], ['bar']]),
	);
});


test('scalar items', function () {
	$schema = Expect::tuple([
		Expect::string(),
		Expect::int(),
		Expect::bool(),
		Expect::scalar(),
		Expect::type('string'),
		Expect::type('int'),
		Expect::string('abc'),
		Expect::string(123),
		Expect::type('string')->default(123),
		Expect::anyOf(1, 2),
	]);

	Assert::equal(
		[null, null, null, null, null, null, 'abc', 123, 123, null],
		(new Processor)->process($schema, []),
	);
});


testException(
	'default value must be readonly',
	fn() => Expect::tuple([])->default([]),
	Nette\InvalidStateException::class,
);


test('with items', function () {
	$schema = Expect::tuple([
		Expect::string(),
		Expect::arrayOf('string'),
	]);

	$processor = new Processor;

	Assert::equal(
		[null, []],
		$processor->process($schema, []),
	);

	Assert::equal(
		[null, []],
		$processor->processMultiple($schema, []),
	);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->process($schema, [1, 2, 3]);
	}, [
		"Unexpected item '2'.",
		"The item '0' expects to be string, 1 given.",
		"The item '1' expects to be array, 2 given.",
	]);

	Assert::equal(
		['newval3', []],
		$processor->process($schema, ['newval3']),
	);

	Assert::equal(
		['newval4', []],
		$processor->processMultiple($schema, [['newval2', 'newval3'], ['newval4']]),
	);
});


test('extend', function () {
	$schema = Expect::structure([Expect::string(), Expect::string()]);

	Assert::equal(
		Expect::structure([Expect::string(), Expect::string(), Expect::int()]),
		$schema->extend([Expect::int()]),
	);

	Assert::equal(
		Expect::structure([Expect::string(), Expect::string(), Expect::int()]),
		$schema->extend(Expect::structure([Expect::int()])),
	);
});


test('getShape', function () {
	Assert::equal(
		[Expect::int(), Expect::string()],
		Expect::tuple([Expect::int(), Expect::string()])->getShape(),
	);
});
