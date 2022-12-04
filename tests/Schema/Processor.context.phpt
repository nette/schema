<?php

declare(strict_types=1);

use Nette\Schema\Context;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$schema = Expect::structure([
		'r' => Expect::string()->required(),
	]);

	$processor = new Processor;
	$processor->onNewContext[] = function (Context $context) {
		$context->path = ['first'];
	};

	$e = checkValidationErrors(function () use ($schema, $processor) {
		$processor->process($schema, []);
	}, ["The mandatory item 'first\u{a0}›\u{a0}r' is missing."]);

	Assert::equal(
		[
			new Nette\Schema\Message(
				'The mandatory item %path% is missing.',
				Nette\Schema\Message::MissingItem,
				['first', 'r'],
				['isKey' => false]
			),
		],
		$e->getMessageObjects()
	);
});
