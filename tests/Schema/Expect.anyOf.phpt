<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('with scalars', function () {
	$schema = Expect::anyOf('one', true, Expect::int());

	Assert::same('one', (new Processor)->process($schema, 'one'));

	Assert::same(true, (new Processor)->process($schema, true));

	Assert::same(123, (new Processor)->process($schema, 123));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, false);
	}, ["The option expects to be 'one'|true|int, false given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'two');
	}, ["The option expects to be 'one'|true|int, 'two' given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, null);
	}, ["The option expects to be 'one'|true|int, null given."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ["The option expects to be 'one'|true|int, array given."]);
});


test('with complex structure', function () {
	$schema = Expect::anyOf(Expect::listOf('string'), true, Expect::int());

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, false);
	}, ['The option expects to be list|true|int, false given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [123]);
	}, ["The option '0' expects to be string, int 123 given."]);

	Assert::same(['foo'], (new Processor)->process($schema, ['foo']));
});


test('with asserts', function () {
	$schema = Expect::anyOf(Expect::string()->assert('strlen'), true);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ["Failed assertion strlen() for option with value ''."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The option expects to be string|true, 123 given.']);

	Assert::same('foo', (new Processor)->process($schema, 'foo'));
});


test('no default value', function () {
	$schema = Expect::structure([
		'key1' => Expect::anyOf(Expect::string(), Expect::int()),
		'key2' => Expect::anyOf(Expect::string('default'), true, Expect::int()),
		'key3' => Expect::anyOf(true, Expect::string('default'), Expect::int()),
	]);

	Assert::equal(
		(object) ['key1' => null, 'key2' => null, 'key3' => null],
		(new Processor)->process($schema, [])
	);
});


test('required', function () {
	$schema = Expect::structure([
		'key1' => Expect::anyOf(Expect::string(), Expect::int())->required(),
		'key2' => Expect::anyOf(Expect::string('default'), true, Expect::int())->required(),
		'key3' => Expect::anyOf(true, Expect::string('default'), Expect::int())->required(),
		'key4' => Expect::anyOf(Expect::string()->nullable(), Expect::int())->required(),
		'key5' => Expect::anyOf(true, Expect::string('default'), Expect::int())->required()->nullable(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, [
		"The mandatory option 'key1' is missing.",
		"The mandatory option 'key2' is missing.",
		"The mandatory option 'key3' is missing.",
		"The mandatory option 'key4' is missing.",
		"The mandatory option 'key5' is missing.",
	]);
});


test('required as argument', function () {
	$schema = Expect::structure([
		'key1' => Expect::anyOf(Expect::string(), Expect::int())->required(),
		'key1nr' => Expect::anyOf(Expect::string(), Expect::int())->required(false),
		'key2' => Expect::anyOf(Expect::string('default'), true, Expect::int())->required(),
		'key2nr' => Expect::anyOf(Expect::string('default'), true, Expect::int())->required(false),
		'key3' => Expect::anyOf(true, Expect::string('default'), Expect::int())->required(),
		'key3nr' => Expect::anyOf(true, Expect::string('default'), Expect::int())->required(false),
		'key4' => Expect::anyOf(Expect::string()->nullable(), Expect::int())->required(),
		'key4nr' => Expect::anyOf(Expect::string()->nullable(), Expect::int())->required(false),
		'key5' => Expect::anyOf(true, Expect::string('default'), Expect::int())->required()->nullable(),
		'key5nr' => Expect::anyOf(true, Expect::string('default'), Expect::int())->required(false)->nullable(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, [
		"The mandatory option 'key1' is missing.",
		"The mandatory option 'key2' is missing.",
		"The mandatory option 'key3' is missing.",
		"The mandatory option 'key4' is missing.",
		"The mandatory option 'key5' is missing.",
	]);
});


test('not nullable', function () {
	$schema = Expect::structure([
		'key1' => Expect::anyOf(Expect::string(), Expect::int()),
		'key2' => Expect::anyOf(Expect::string('default'), true, Expect::int()),
		'key3' => Expect::anyOf(true, Expect::string('default'), Expect::int()),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['key1' => null, 'key2' => null, 'key3' => null]);
	}, [
		"The option 'key1' expects to be string|int, null given.",
		"The option 'key2' expects to be string|true|int, null given.",
		"The option 'key3' expects to be true|string|int, null given.",
	]);
});


test('required & nullable', function () {
	$schema = Expect::structure([
		'key1' => Expect::anyOf(Expect::string()->nullable(), Expect::int())->required(),
		'key2' => Expect::anyOf(Expect::string('default'), true, Expect::int(), null)->required(),
		'key3' => Expect::anyOf(true, Expect::string('default'), Expect::int())->required()->nullable(),
	]);

	Assert::equal(
		(object) ['key1' => null, 'key2' => null, 'key3' => null],
		(new Processor)->process($schema, ['key1' => null, 'key2' => null, 'key3' => null])
	);
});


test('nullable anyOf', function () {
	$schema = Expect::anyOf(Expect::string(), true)->nullable();

	Assert::same('one', (new Processor)->process($schema, 'one'));

	Assert::same(null, (new Processor)->process($schema, null));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, false);
	}, ['The option expects to be string|true|null, false given.']);
});


test('processing', function () {
	$schema = Expect::anyOf(Expect::string(), true)->nullable();
	$processor = new Processor;

	Assert::same('one', $processor->process($schema, 'one'));
	Assert::same(null, $processor->process($schema, null));

	checkValidationErrors(function () use ($schema, $processor) {
		$processor->process($schema, false);
	}, ['The option expects to be string|true|null, false given.']);


	Assert::same('two', $processor->processMultiple($schema, ['one', 'two']));
	Assert::same(null, $processor->processMultiple($schema, ['one', null]));
});


test('Schema as default value', function () {
	$default = Expect::structure([
		'key2' => Expect::string(),
	])->castTo('array');

	$schema = Expect::structure([
		'key1' => Expect::anyOf(false, $default)->default($default),
	])->castTo('array');

	Assert::same(['key1' => ['key2' => null]], (new Processor)->process($schema, null));
});


test('normalization', function () {
	$schema = Expect::anyOf(
		Expect::string()->before(function ($v) { return (string) $v; })
	);
	Assert::same('1', (new Processor)->process($schema, 1));
});
