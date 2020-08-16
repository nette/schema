<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$schema = Expect::string()->pattern('\d{9}');

	Assert::same('123456789', (new Processor)->process($schema, '123456789'));
});


test('', function () {
	$schema = Expect::string()->pattern('\d{9}');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '123');
	}, ["The option expects to match pattern '\\d{9}', '123' given."]);
});
