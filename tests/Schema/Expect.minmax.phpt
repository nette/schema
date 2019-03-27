<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // int & min
	$schema = Expect::int()->min(10);

	Assert::same(10, (new Processor)->process($schema, 10));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 9);
	}, ['The option expects to be int in range 10.., int 9 given.']);
});


test(function () { // int & max
	$schema = Expect::int()->max(20);

	Assert::same(20, (new Processor)->process($schema, 20));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 21);
	}, ['The option expects to be int in range ..20, int 21 given.']);
});


test(function () { // int & min & max
	$schema = Expect::int()->min(10)->max(20);

	Assert::same(10, (new Processor)->process($schema, 10));
	Assert::same(20, (new Processor)->process($schema, 20));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 9);
	}, ['The option expects to be int in range 10..20, int 9 given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 21);
	}, ['The option expects to be int in range 10..20, int 21 given.']);
});


test(function () { // string
	$schema = Expect::string()->min(1)->max(5);

	Assert::same('hello', (new Processor)->process($schema, 'hello'));
	Assert::same('x', (new Processor)->process($schema, 'x'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ["The option expects to be string in range 1..5, string '' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'foobar');
	}, ["The option expects to be string in range 1..5, string 'foobar' given."]);
});


test(function () { // array
	$schema = Expect::array()->min(1)->max(3);

	Assert::same([1], (new Processor)->process($schema, [1]));
	Assert::same([1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The option expects to be array in range 1..3, array given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3, 4]);
	}, ['The option expects to be array in range 1..3, array given.']);
});


test(function () { // structure
	$schema = Expect::structure([])->otherItems('int')->min(1)->max(3);

	Assert::equal((object) [1], (new Processor)->process($schema, [1]));
	Assert::equal((object) [1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The option expects to be array in range 1..3, array given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3, 4]);
	}, ['The option expects to be array in range 1..3, array given.']);
});
