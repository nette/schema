<?php declare(strict_types=1);

use Nette\Schema\{Expect, Processor};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('pattern validates matching string', function () {
	$schema = Expect::string()->pattern('\d{9}');

	Assert::same('123456789', (new Processor)->process($schema, '123456789'));
});


test('pattern rejects non-matching string', function () {
	$schema = Expect::string()->pattern('\d{9}');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '123');
	}, ["The item expects to match pattern '\\d{9}', '123' given."]);
});


test('nullable pattern accepts null without validation', function () {
	$schema = Expect::string()->nullable()->pattern('\d{9}');

	Assert::same(null, (new Processor)->process($schema, null));
});


test('an invalid pattern is an error of the schema, not of the value', function () {
	Assert::exception(
		fn() => (new Processor)->process(Expect::string()->pattern('(a'), 'a'),
		Nette\InvalidStateException::class,
		"Invalid pattern '(a': Compilation failed: missing closing parenthesis at offset %d%",
	);

	// a value the pattern cannot be applied to is still a validation error
	checkValidationErrors(function () {
		(new Processor)->process(Expect::string()->pattern('.*'), "\xFF");
	}, ["The item expects to match pattern '.*', '\xFF' given."]);
});
