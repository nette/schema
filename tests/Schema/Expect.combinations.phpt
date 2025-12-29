<?php

declare(strict_types=1);

use Nette\Schema\Context;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('chaining before, assert, and transform', function () {
	$schema = Expect::string()
		->before(fn($v) => strtolower($v))
		->assert(fn($s) => ctype_alpha($s), 'Must contain only letters')
		->transform(fn($v) => ucfirst($v));

	Assert::same('Hello', (new Processor)->process($schema, 'HELLO'));
	Assert::same('World', (new Processor)->process($schema, 'world'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'HELLO123');
	}, ["Failed assertion 'Must contain only letters' for item with value 'hello123'."]);
});


test('multiple assertions in sequence', function () {
	$schema = Expect::string()
		->assert(ctype_digit(...), 'Must be numeric')
		->assert(fn($s) => strlen($s) >= 3, 'Too short')
		->assert(fn($s) => strlen($s) <= 10, 'Too long');

	Assert::same('12345', (new Processor)->process($schema, '12345'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'abc');
	}, ["Failed assertion 'Must be numeric' for item with value 'abc'."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '12');
	}, ["Failed assertion 'Too short' for item with value '12'."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '12345678901');
	}, ["Failed assertion 'Too long' for item with value '12345678901'."]);
});


test('pattern() combined with min/max', function () {
	$schema = Expect::string()
		->pattern('[a-z]+')
		->min(3)
		->max(10);

	Assert::same('hello', (new Processor)->process($schema, 'hello'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'Hello');
	}, ["The item expects to match pattern '[a-z]+', 'Hello' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'ab');
	}, ['The length of item expects to be in range 3..10, 2 bytes given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'abcdefghijk');
	}, ['The length of item expects to be in range 3..10, 11 bytes given.']);
});


test('transform with validation using context', function () {
	$schema = Expect::string()
		->transform(function (string $s, Context $context) {
			if (!ctype_lower($s)) {
				$context->addError('All characters must be lowercase', 'validation.lowercase');
				return null;
			}
			return strtoupper($s);
		});

	Assert::same('HELLO', (new Processor)->process($schema, 'hello'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'Hello');
	}, ['All characters must be lowercase']);
});


test('multiple transforms in sequence', function () {
	$schema = Expect::string()
		->transform(fn($s) => trim($s))
		->transform(fn($s) => strtolower($s))
		->transform(fn($s) => ucwords($s));

	Assert::same('Hello World', (new Processor)->process($schema, '  HELLO WORLD  '));
});


test('assert() and transform interleaved', function () {
	$schema = Expect::int()
		->assert(fn($v) => $v > 0, 'Must be positive')
		->transform(fn($v) => $v * 2)
		->assert(fn($v) => $v < 100, 'Result too large')
		->transform(fn($v) => (string) $v);

	Assert::same('20', (new Processor)->process($schema, 10));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, -5);
	}, ["Failed assertion 'Must be positive' for item with value -5."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 60);
	}, ["Failed assertion 'Result too large' for item with value 120."]);
});


test('before() normalization with type conversion', function () {
	$schema = Expect::int()
		->before(fn($v) => is_string($v) ? (int) $v : $v)
		->min(1)
		->max(100);

	Assert::same(42, (new Processor)->process($schema, '42'));
	Assert::same(42, (new Processor)->process($schema, 42));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '0');
	}, ['The item expects to be in range 1..100, 0 given.']);
});


test('castTo() combined with transformations', function () {
	$schema = Expect::string()
		->pattern('\d{4}-\d{2}-\d{2}')
		->castTo(DateTime::class);

	$result = (new Processor)->process($schema, '2024-01-15');
	Assert::type(DateTime::class, $result);
	Assert::same('2024-01-15', $result->format('Y-m-d'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'invalid-date');
	}, ["The item expects to match pattern '\\d{4}-\\d{2}-\\d{2}', 'invalid-date' given."]);
});


test('nullable with all validators', function () {
	$schema = Expect::string()
		->nullable()
		->pattern('[a-z]+')
		->min(3)
		->transform(fn($v) => $v ? strtoupper($v) : null);

	Assert::same('HELLO', (new Processor)->process($schema, 'hello'));
	Assert::same(null, (new Processor)->process($schema, null));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'ab');
	}, ['The length of item expects to be in range 3.., 2 bytes given.']);
});


test('deprecated combined with other validators', function () {
	$schema = Expect::string()
		->deprecated('Use newField instead')
		->pattern('\w+');

	$processor = new Processor;
	Assert::same('test', $processor->process($schema, 'test'));
	Assert::same(['Use newField instead'], $processor->getWarnings());
});


test('deprecated does not warn when value not provided', function () {
	$schema = Expect::structure([
		'oldField' => Expect::string()->deprecated('Use newField instead'),
		'newField' => Expect::string(),
	]);

	$processor = new Processor;
	Assert::equal(
		(object) ['oldField' => null, 'newField' => 'value'],
		$processor->process($schema, ['newField' => 'value']),
	);
	Assert::same([], $processor->getWarnings());
});


test('complex validation chain on array items', function () {
	$schema = Expect::arrayOf(
		Expect::string()
			->pattern('[a-z]+')
			->min(2)
			->transform(fn($s) => ucfirst($s)),
	);

	Assert::same(['Hello', 'World'], (new Processor)->process($schema, ['hello', 'world']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['hello', 'X']);
	}, ["The length of item '1' expects to be in range 2.., 1 bytes given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['hello', 'World']);
	}, ["The item '1' expects to match pattern '[a-z]+', 'World' given."]);
});
