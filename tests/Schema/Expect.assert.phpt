<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // single assertion
	$schema = Expect::string()->assert('is_file');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'hello');
	}, ["Failed assertion is_file() for option with value 'hello'."]);

	Assert::same(__FILE__, (new Processor)->process($schema, __FILE__));
});


test(function () { // multiple assertions
	$schema = Expect::string()->assert('ctype_digit')->assert(function ($s) { return strlen($s) >= 3; });

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ["Failed assertion ctype_digit() for option with value ''."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '1');
	}, ["Failed assertion #1 for option with value '1'."]);

	Assert::same('123', (new Processor)->process($schema, '123'));
});
