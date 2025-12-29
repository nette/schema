<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('error path includes full hierarchy for nested items', function () {
	$schema = Expect::structure([
		'database' => Expect::structure([
			'primary' => Expect::structure([
				'host' => Expect::string()->required(),
			]),
		]),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ["The mandatory item 'database\u{a0}›\u{a0}primary\u{a0}›\u{a0}host' is missing."]);
});


test('error suggests similar key names for typos', function () {
	$schema = Expect::structure([
		'username' => Expect::string(),
		'password' => Expect::string(),
		'database' => Expect::string(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['usernane' => 'john']);
	}, ["Unexpected item 'usernane', did you mean 'username'?"]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['datbase' => 'test']);
	}, ["Unexpected item 'datbase', did you mean 'database'?"]);
});


test('error shows expected vs actual values clearly', function () {
	$schema = Expect::int()->min(10)->max(100);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 5);
	}, ['The item expects to be in range 10..100, 5 given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 150);
	}, ['The item expects to be in range 10..100, 150 given.']);
});


test('error messages for union types list all alternatives', function () {
	$schema = Expect::type('string|int|bool');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The item expects to be string or int or bool, array given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, null);
	}, ['The item expects to be string or int or bool, null given.']);
});


test('anyOf error shows all attempted variants', function () {
	$schema = Expect::anyOf('foo', 'bar', 'baz');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'qux');
	}, ["The item expects to be 'foo'|'bar'|'baz', 'qux' given."]);
});


test('multiple validation errors collected and reported together', function () {
	$schema = Expect::structure([
		'name' => Expect::string()->required(),
		'age' => Expect::int()->min(0)->max(150)->required(),
		'email' => Expect::string()->pattern('[^@]+@[^@]+')->required(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [
			'age' => -5,
			'email' => 'invalid',
		]);
	}, [
		"The mandatory item 'name' is missing.",
		"The item 'age' expects to be in range 0..150, -5 given.",
		"The item 'email' expects to match pattern '[^@]+@[^@]+', 'invalid' given.",
	]);
});


test('error in array item includes index in path', function () {
	$schema = Expect::arrayOf(Expect::int()->min(0));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, -3, 4, -5]);
	}, [
		"The item '2' expects to be in range 0.., -3 given.",
		"The item '4' expects to be in range 0.., -5 given.",
	]);
});


test('pattern error shows the pattern and provided value', function () {
	$schema = Expect::string()->pattern('\d{3}-\d{4}');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '123-45');
	}, ["The item expects to match pattern '\\d{3}-\\d{4}', '123-45' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'abc-defg');
	}, ["The item expects to match pattern '\\d{3}-\\d{4}', 'abc-defg' given."]);
});


test('custom assertion error uses provided description', function () {
	$schema = Expect::string()
		->assert(fn($v) => strlen($v) > 0, 'String cannot be empty')
		->assert(fn($v) => !str_contains($v, ' '), 'String cannot contain spaces');

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ["Failed assertion 'String cannot be empty' for item with value ''."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'hello world');
	}, ["Failed assertion 'String cannot contain spaces' for item with value 'hello world'."]);
});


test('error for unexpected extra keys', function () {
	$schema = Expect::structure([
		'foo' => Expect::string(),
		'bar' => Expect::string(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['foo' => 'a', 'bar' => 'b', 'baz' => 'c']);
	}, ["Unexpected item 'baz', did you mean 'bar'?"]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['extra1' => 'x', 'extra2' => 'y']);
	}, [
		"Unexpected item 'extra1'.",
		"Unexpected item 'extra2'.",
	]);
});


test('anyOf with structures shows inner validation errors', function () {
	$schema = Expect::anyOf(
		Expect::structure([
			'type' => Expect::anyOf('A')->required(),
			'fieldA' => Expect::int()->required(),
		]),
		Expect::structure([
			'type' => Expect::anyOf('B')->required(),
			'fieldB' => Expect::string()->required(),
		]),
	);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['type' => 'A']);
	}, [
		"The mandatory item 'fieldA' is missing.",
		"The item 'type' expects to be 'B', 'A' given.",
		"The mandatory item 'fieldB' is missing.",
	]);
});


test('transform error with context provides clear message', function () {
	$schema = Expect::int()->transform(function ($val, Nette\Schema\Context $context) {
		if ($val < 0) {
			$context->addError('Negative numbers are not allowed', 'custom.negative');
			return null;
		}
		return $val * 2;
	});

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, -10);
	}, ['Negative numbers are not allowed']);
});


test('unicode string length validation error', function () {
	$schema = Expect::unicode()->min(3)->max(10);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'žš');
	}, ['The length of item expects to be in range 3..10, 2 characters given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'žščřďťňáíéů');
	}, ['The length of item expects to be in range 3..10, 11 characters given.']);
});


test('deprecation warnings do not interfere with validation', function () {
	$schema = Expect::structure([
		'oldField' => Expect::string()->deprecated('Use newField'),
		'newField' => Expect::string()->required(),
	]);

	$processor = new Processor;

	checkValidationErrors(function () use ($schema, $processor) {
		$processor->process($schema, ['oldField' => 'value']);
	}, ["The mandatory item 'newField' is missing."]);

	Assert::same(['Use newField'], $processor->getWarnings());
});
