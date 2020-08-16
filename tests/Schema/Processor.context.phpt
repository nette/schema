<?php

declare(strict_types=1);

use Nette\Schema\Context;
use Nette\Schema\Expect;
use Nette\Schema\Processor;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$schema = Expect::structure([
		'r' => Expect::string()->required(),
	]);

	$processor = new Processor;
	$processor->onNewContext[] = function (Context $context) {
		$context->path = ['first'];
	};

	checkValidationErrors(function () use ($schema, $processor) {
		$processor->process($schema, []);
	}, ["The mandatory option 'first › r' is missing."]);
});
