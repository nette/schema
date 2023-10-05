<?php

declare(strict_types=1);

use Nette\Schema\Context;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('simple tranformation', function () {
	$schema = Expect::string()->transform(function ($s) { return strrev($s); });

	Assert::same('olleh', (new Processor)->process($schema, 'hello'));
});


test('validation via transform', function () {
	$schema = Expect::int()
		->transform(function ($val, Context $context) {
			if ($val > 3) {
				$context->addError('Bigger than 3', 'my');
			}
			return $val;
		})
		->transform(function ($s, Context $context) {
			if ($s > 5) {
				$context->addError('Bigger than 5', 'my');
			}
			return $s;
		});

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 10);
	}, ['Bigger than 3']);

	Assert::same(2, (new Processor)->process($schema, 2));
});
