<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('single assertion', function () {
	$schema = Expect::string()->assert('is_file');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'hello');
	}, ["Failed assertion is_file() for item with value 'hello'."]);

	Assert::same(__FILE__, (new Processor)->process($schema, __FILE__));
});


test('multiple assertions', function () {
	$schema = Expect::string()->assert('ctype_digit')->assert(fn($s) => strlen($s) >= 3);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ["Failed assertion ctype_digit() for item with value ''."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '1');
	}, ["Failed assertion #1 for item with value '1'."]);

	Assert::same('123', (new Processor)->process($schema, '123'));
});


test('multiple assertions with custom descriptions', function () {
	$schema = Expect::string()
		->assert('ctype_digit', 'Is number')
		->assert(fn($s) => strlen($s) >= 3, 'Minimal lenght');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ["Failed assertion 'Is number' for item with value ''."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '1');
	}, ["Failed assertion 'Minimal lenght' for item with value '1'."]);

	Assert::same('123', (new Processor)->process($schema, '123'));
});
