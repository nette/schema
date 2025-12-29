<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('nested structure with mixed required and optional', function () {
	$schema = Expect::structure([
		'required1' => Expect::structure([
			'required2' => Expect::string()->required(),
			'optional1' => Expect::string(),
		]),
		'optional2' => Expect::structure([
			'value' => Expect::string(),
		]),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ["The mandatory item 'required1\u{a0}›\u{a0}required2' is missing."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['required1' => []]);
	}, ["The mandatory item 'required1\u{a0}›\u{a0}required2' is missing."]);

	Assert::equal(
		(object) [
			'required1' => (object) ['required2' => 'value', 'optional1' => null],
			'optional2' => (object) ['value' => null],
		],
		(new Processor)->process($schema, ['required1' => ['required2' => 'value']]),
	);
});


test('optional nested structures can be omitted', function () {
	$schema = Expect::structure([
		'optional' => Expect::structure([
			'value' => Expect::string()->required(),
		])->required(false),
		'required' => Expect::structure([
			'value' => Expect::string(),
		]),
	]);

	Assert::equal(
		(object) [
			'optional' => null,
			'required' => (object) ['value' => null],
		],
		(new Processor)->process($schema, []),
	);

	Assert::equal(
		(object) [
			'optional' => (object) ['value' => 'test'],
			'required' => (object) ['value' => null],
		],
		(new Processor)->process($schema, ['optional' => ['value' => 'test']]),
	);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['optional' => []]);
	}, ["The mandatory item 'optional\u{a0}›\u{a0}value' is missing."]);
});


test('nested structures with default values', function () {
	$schema = Expect::structure([
		'database' => Expect::structure([
			'host' => Expect::string()->default('localhost'),
			'port' => Expect::int()->default(3306),
			'credentials' => Expect::structure([
				'username' => Expect::string()->required(),
				'password' => Expect::string()->required(),
			]),
		]),
	]);

	Assert::equal(
		(object) [
			'database' => (object) [
				'host' => 'localhost',
				'port' => 3306,
				'credentials' => (object) [
					'username' => 'root',
					'password' => 'secret',
				],
			],
		],
		(new Processor)->process($schema, [
			'database' => [
				'credentials' => [
					'username' => 'root',
					'password' => 'secret',
				],
			],
		]),
	);
});


test('nested structure with skipDefaults', function () {
	$schema = Expect::structure([
		'config' => Expect::structure([
			'enabled' => Expect::bool()->default(true),
			'timeout' => Expect::int()->default(30),
			'name' => Expect::string()->required(),
		])->skipDefaults(),
	]);

	$processor = new Processor;
	Assert::equal(
		(object) [
			'config' => (object) [
				'name' => 'test',
			],
		],
		$processor->process($schema, ['config' => ['name' => 'test']]),
	);

	Assert::equal(
		(object) [
			'config' => (object) [
				'enabled' => false,
				'name' => 'test',
			],
		],
		$processor->process($schema, ['config' => ['enabled' => false, 'name' => 'test']]),
	);
});


test('optional nested structure not provided', function () {
	$schema = Expect::structure([
		'required' => Expect::string()->required(),
		'nested' => Expect::structure([
			'value' => Expect::string()->required(),
		])->required(false),
	]);

	Assert::equal(
		(object) [
			'required' => 'test',
			'nested' => null,
		],
		(new Processor)->process($schema, ['required' => 'test']),
	);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['required' => 'test', 'nested' => []]);
	}, ["The mandatory item 'nested\u{a0}›\u{a0}value' is missing."]);
});


test('nested anyOf with structures', function () {
	$schema = Expect::structure([
		'payment' => Expect::anyOf(
			Expect::structure([
				'method' => Expect::anyOf('card')->required(),
				'cardNumber' => Expect::string()->pattern('\d{16}')->required(),
			]),
			Expect::structure([
				'method' => Expect::anyOf('paypal')->required(),
				'email' => Expect::string()->pattern('[^@]+@[^@]+')->required(),
			]),
			Expect::structure([
				'method' => Expect::anyOf('cash')->required(),
			]),
		),
	]);

	Assert::equal(
		(object) [
			'payment' => (object) [
				'method' => 'card',
				'cardNumber' => '1234567890123456',
			],
		],
		(new Processor)->process($schema, [
			'payment' => [
				'method' => 'card',
				'cardNumber' => '1234567890123456',
			],
		]),
	);

	Assert::equal(
		(object) [
			'payment' => (object) [
				'method' => 'cash',
			],
		],
		(new Processor)->process($schema, [
			'payment' => [
				'method' => 'cash',
			],
		]),
	);
});


test('deeply nested with multiple errors at different levels', function () {
	$schema = Expect::structure([
		'a' => Expect::structure([
			'b' => Expect::structure([
				'c' => Expect::string()->required(),
				'd' => Expect::int()->min(10)->required(),
			]),
			'e' => Expect::string()->pattern('\w+')->required(),
		]),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [
			'a' => [
				'b' => ['d' => 5],
				'e' => 'hello world',
			],
		]);
	}, [
		"The mandatory item 'a\u{a0}›\u{a0}b\u{a0}›\u{a0}c' is missing.",
		"The item 'a\u{a0}›\u{a0}b\u{a0}›\u{a0}d' expects to be in range 10.., 5 given.",
		"The item 'a\u{a0}›\u{a0}e' expects to match pattern '\\w+', 'hello world' given.",
	]);
});
