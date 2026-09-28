<?php declare(strict_types=1);

use Nette\Schema\{Context, Expect, Processor};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('getWarnings() before processing', function () {
	Assert::same([], (new Processor)->getWarnings());
});


test('a nested call of the same processor keeps the errors and warnings of the outer run', function () {
	$processor = new Processor;
	$schema = Expect::structure([
		'bad' => Expect::int(),
		'old' => Expect::int()->deprecated(),
		'good' => Expect::int()->transform(fn($v) => $processor->process(Expect::int(), $v)),
	]);

	checkValidationErrors(function () use ($processor, $schema) {
		$processor->process($schema, ['bad' => 'WRONG', 'good' => 1]);
	}, ["The item 'bad' expects to be int, 'WRONG' given."]);

	$processor->process($schema, ['old' => 1, 'good' => 1]);
	Assert::same(["The item 'old' is deprecated."], $processor->getWarnings());
});


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
				['isKey' => false],
			),
		],
		$e->getMessageObjects(),
	);
});
