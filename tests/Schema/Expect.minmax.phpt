<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('int & min', function () {
	$schema = Expect::int()->min(10);

	Assert::same(10, (new Processor)->process($schema, 10));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 9);
	}, ['The item expects to be int in range 10.., 9 given.']);
});


test('int & max', function () {
	$schema = Expect::int()->max(20);

	Assert::same(20, (new Processor)->process($schema, 20));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 21);
	}, ['The item expects to be int in range ..20, 21 given.']);
});


test('int & min & max', function () {
	$schema = Expect::int()->min(10)->max(20);

	Assert::same(10, (new Processor)->process($schema, 10));
	Assert::same(20, (new Processor)->process($schema, 20));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 9);
	}, ['The item expects to be int in range 10..20, 9 given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 21);
	}, ['The item expects to be int in range 10..20, 21 given.']);
});


test('nullable int & min & max', function () {
	$schema = Expect::int()->min(10)->max(20)->nullable();

	Assert::same(null, (new Processor)->process($schema, null));
	Assert::same(15, (new Processor)->process($schema, 15));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 9);
	}, ['The item expects to be null or int in range 10..20, 9 given.']);
});


test('string', function () {
	$schema = Expect::string()->min(1)->max(5);

	Assert::same('hello', (new Processor)->process($schema, 'hello'));
	Assert::same('x', (new Processor)->process($schema, 'x'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ["The item expects to be string in range 1..5, '' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'foobar');
	}, ["The item expects to be string in range 1..5, 'foobar' given."]);
});


test('unicode', function () {
	$schema = Expect::unicode()->min(2)->max(4);

	Assert::same('žšáé', (new Processor)->process($schema, 'žšáé'));
	Assert::same('žš', (new Processor)->process($schema, 'žš'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'ž');
	}, ["The item expects to be unicode in range 2..4, 'ž' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'žšáéx');
	}, ["The item expects to be unicode in range 2..4, 'žšáéx' given."]);
});


test('array', function () {
	$schema = Expect::array()->min(1)->max(3);

	Assert::same([1], (new Processor)->process($schema, [1]));
	Assert::same([1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The item expects to be array in range 1..3, array given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3, 4]);
	}, ['The item expects to be array in range 1..3, array given.']);
});


test('structure', function () {
	$schema = Expect::structure([])->otherItems('int')->min(1)->max(3);

	Assert::equal((object) [1], (new Processor)->process($schema, [1]));
	Assert::equal((object) [1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The item expects to be array in range 1..3, array given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3, 4]);
	}, ['The item expects to be array in range 1..3, array given.']);
});
