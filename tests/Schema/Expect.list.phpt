<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // without default value
	$schema = Expect::list();

	Assert::same([], (new Processor)->process($schema, []));

	Assert::same(['a', 'b', 'c'], (new Processor)->process($schema, ['a', 'b', 'c']));

	checkValidationErrors(function () use ($schema) {
		Assert::same(['key' => 'val'], (new Processor)->process($schema, ['key' => 'val']));
	}, ['The option expects to be list, array given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'one');
	}, ["The option expects to be list, string 'one' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, true);
	}, ['The option expects to be list, bool given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The option expects to be list, int 123 given.']);

	Assert::same([], (new Processor)->process($schema, null));
});


test(function () { // merging
	$schema = Expect::list([1, 2, 3]);

	Assert::same([1, 2, 3], (new Processor)->process($schema, []));

	Assert::same([1, 2, 3, 'a', 'b', 'c'], (new Processor)->process($schema, ['a', 'b', 'c']));

	Assert::same([1, 2, 3], (new Processor)->process($schema, null));
});


test(function () { // merging & other items validation
	$schema = Expect::list([1, 2, 3])->items('string');

	Assert::same([1, 2, 3], (new Processor)->process($schema, []));

	Assert::same([1, 2, 3, 'a', 'b', 'c'], (new Processor)->process($schema, ['a', 'b', 'c']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"The option '0' expects to be string, int 1 given.",
		"The option '1' expects to be string, int 2 given.",
		"The option '2' expects to be string, int 3 given.",
	]);

	Assert::same([1, 2, 3], (new Processor)->process($schema, null));
});


test(function () { // listOf() & scalar
	$schema = Expect::listOf('string');

	Assert::same([], (new Processor)->process($schema, []));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3]);
	}, [
		"The option '0' expects to be string, int 1 given.",
		"The option '1' expects to be string, int 2 given.",
		"The option '2' expects to be string, int 3 given.",
	]);

	Assert::same(['val', 'val'], (new Processor)->process($schema, ['val', 'val']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['key' => 'val']);
	}, ['The option expects to be list, array given.']);
});


test(function () { // listOf() & error
	Assert::exception(function () {
		Expect::listOf(['a' => Expect::string()]);
	}, TypeError::class);
});
