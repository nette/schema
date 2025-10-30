<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('without default value', function () {
	$schema = Expect::list();

	Assert::same([], (new Processor)->process($schema, []));

	Assert::same(['a', 'b', 'c'], (new Processor)->process($schema, ['a', 'b', 'c']));

	checkValidationErrors(function () use ($schema) {
		Assert::same(['key' => 'val'], (new Processor)->process($schema, ['key' => 'val']));
	}, ['The item expects to be list, array given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'one');
	}, ["The item expects to be list, 'one' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, true);
	}, ['The item expects to be list, true given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The item expects to be list, 123 given.']);

	Assert::same([], (new Processor)->process($schema, null));
});


test('not merging default value', function () {
	$schema = Expect::list([1, 2, 3]);

	Assert::same([], (new Processor)->process($schema, []));

	Assert::same(['a', 'b', 'c'], (new Processor)->process($schema, ['a', 'b', 'c']));

	Assert::same([], (new Processor)->process($schema, null));
});


test('merging default value', function () {
	$schema = @Expect::list([1, 2, 3])->mergeDefaults(true); // mergeDefaults() is deprecated

	Assert::same([1, 2, 3], (new Processor)->process($schema, []));

	Assert::same([1, 2, 3, 'a', 'b', 'c'], (new Processor)->process($schema, ['a', 'b', 'c']));

	Assert::same([1, 2, 3], (new Processor)->process($schema, null));
});


test('merging default value & other items validation', function () {
	$schema = @Expect::list([1, 2, 3])->mergeDefaults(true)->items('string'); // mergeDefaults() is deprecated

	Assert::same([1, 2, 3], (new Processor)->process($schema, []));

	Assert::same([1, 2, 3, 'a', 'b', 'c'], (new Processor)->process($schema, ['a', 'b', 'c']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"The item '0' expects to be string, 1 given.",
		"The item '1' expects to be string, 2 given.",
		"The item '2' expects to be string, 3 given.",
	]);

	Assert::same([1, 2, 3], (new Processor)->process($schema, null));
});


test('listOf() & scalar', function () {
	$schema = Expect::listOf('string');

	Assert::same([], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"The item '0' expects to be string, 1 given.",
		"The item '1' expects to be string, 2 given.",
		"The item '2' expects to be string, 3 given.",
	]);

	Assert::same(['val', 'val'], (new Processor)->process($schema, ['val', 'val']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['key' => 'val']);
	}, ['The item expects to be list, array given.']);
});


testException(
	'listOf() & error',
	fn() => Expect::listOf(['a' => Expect::string()]),
	TypeError::class,
);
