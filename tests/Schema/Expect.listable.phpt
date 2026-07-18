<?php declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('single value is normalized to a list', function () {
	$schema = Expect::listable('string');

	Assert::same(['a'], (new Processor)->process($schema, 'a'));
	Assert::same(['a', 'b'], (new Processor)->process($schema, ['a', 'b']));
	Assert::same([], (new Processor)->process($schema, []));
	Assert::same([], (new Processor)->process($schema, null));
});


test('items are validated', function () {
	$schema = Expect::listable('string');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ["The item '0' expects to be string, 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['a', 123]);
	}, ["The item '1' expects to be string, 123 given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['key' => 'val']);
	}, ['The item expects to be list, array given.']);
});


test('item can be a schema', function () {
	$schema = Expect::listable(Expect::int()->min(1));

	Assert::same([5], (new Processor)->process($schema, 5));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 0);
	}, ["The item '0' expects to be in range 1.., 0 given."]);
});


test('layers merge as lists', function () {
	$schema = Expect::structure([
		'emails' => Expect::listable('string'),
	]);

	Assert::equal(
		(object) ['emails' => ['a', 'b', 'c']],
		(new Processor)->processMultiple($schema, [
			['emails' => 'a'],
			['emails' => ['b', 'c']],
		]),
	);
});
