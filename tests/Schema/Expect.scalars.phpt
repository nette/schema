<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$schema = Expect::scalar();

	Assert::same('hello', (new Processor)->process($schema, 'hello'));
	Assert::same(123, (new Processor)->process($schema, 123));
	Assert::same(false, (new Processor)->process($schema, false));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, null);
	}, ['The item expects to be scalar, null given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The item expects to be scalar, array given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, new class implements Nette\Schema\DynamicParameter {
		});
	}, ['The item expects to be scalar, dynamic given.']);
});


test('', function () {
	$schema = Expect::string();

	Assert::same('hello', (new Processor)->process($schema, 'hello'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The item expects to be string, 123 given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, null);
	}, ['The item expects to be string, null given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, false);
	}, ['The item expects to be string, false given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The item expects to be string, array given.']);
});


test('', function () {
	$schema = Expect::type('string|bool');

	Assert::same('one', (new Processor)->process($schema, 'one'));

	Assert::same(true, (new Processor)->process($schema, true));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The item expects to be string or bool, 123 given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, null);
	}, ['The item expects to be string or bool, null given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The item expects to be string or bool, array given.']);
});


test('', function () {
	$schema = Expect::type('string')->nullable();

	Assert::same('one', (new Processor)->process($schema, 'one'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The item expects to be null or string, 123 given.']);

	Assert::same(null, (new Processor)->process($schema, null));
});
