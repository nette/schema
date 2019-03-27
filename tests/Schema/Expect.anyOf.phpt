<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // with scalars
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


test(function () { // with complex structure
	$schema = Expect::anyOf(Expect::listOf('string'), true, Expect::int());

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, false);
	}, ['The option expects to be list|true|int, false given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [123]);
	}, ["The option '0' expects to be string, int 123 given."]);

	Assert::same(['foo'], (new Processor)->process($schema, ['foo']));
});


test(function () { // with asserts
	$schema = Expect::anyOf(Expect::string()->assert('strlen'), true);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ["Failed assertion strlen() for option with value ''."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 123);
	}, ['The option expects to be string|true, 123 given.']);

	Assert::same('foo', (new Processor)->process($schema, 'foo'));
});


test(function () { // no default value
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


test(function () { // required
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


test(function () { // not nullable
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


test(function () { // required & nullable
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


test(function () { // nullable anyOf
	$schema = Expect::anyOf(Expect::string(), true)->nullable();

	Assert::same('one', (new Processor)->process($schema, 'one'));

	Assert::same(null, (new Processor)->process($schema, null));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, false);
	}, ['The option expects to be string|true|null, false given.']);
});


test(function () { // processing
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
