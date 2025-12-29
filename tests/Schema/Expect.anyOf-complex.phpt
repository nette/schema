<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('discriminated union with valid variant one', function () {
	$schema = Expect::anyOf(
		Expect::structure([
			'type' => Expect::anyOf('session')->required(),
			'expiration' => Expect::string(),
		]),
		Expect::structure([
			'type' => Expect::anyOf('cookie')->required(),
			'name' => Expect::string()->required(),
			'domain' => Expect::string(),
		]),
	);

	Assert::equal(
		(object) ['type' => 'session', 'expiration' => '20 minutes'],
		(new Processor)->process($schema, ['type' => 'session', 'expiration' => '20 minutes']),
	);
});


test('discriminated union with valid variant two', function () {
	$schema = Expect::anyOf(
		Expect::structure([
			'type' => Expect::anyOf('session')->required(),
			'expiration' => Expect::string(),
		]),
		Expect::structure([
			'type' => Expect::anyOf('cookie')->required(),
			'name' => Expect::string()->required(),
			'domain' => Expect::string(),
		]),
	);

	Assert::equal(
		(object) ['type' => 'cookie', 'name' => 'auth', 'domain' => null],
		(new Processor)->process($schema, ['type' => 'cookie', 'name' => 'auth']),
	);
});


test('discriminated union with invalid discriminator', function () {
	$schema = Expect::anyOf(
		Expect::structure([
			'type' => Expect::anyOf('session')->required(),
			'expiration' => Expect::string(),
		]),
		Expect::structure([
			'type' => Expect::anyOf('cookie')->required(),
			'name' => Expect::string()->required(),
		]),
	);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['type' => 'redis']);
	}, [
		"The item 'type' expects to be 'session', 'redis' given.",
		"The item 'type' expects to be 'cookie', 'redis' given.",
		"The mandatory item 'name' is missing.",
	]);
});


test('discriminated union with missing required field', function () {
	$schema = Expect::anyOf(
		Expect::structure([
			'type' => Expect::anyOf('session')->required(),
			'expiration' => Expect::string(),
		]),
		Expect::structure([
			'type' => Expect::anyOf('cookie')->required(),
			'name' => Expect::string()->required(),
		]),
	);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['type' => 'cookie']);
	}, [
		"The item 'type' expects to be 'session', 'cookie' given.",
		"The mandatory item 'name' is missing.",
	]);
});


test('discriminated union with three variants', function () {
	$schema = Expect::anyOf(
		Expect::structure([
			'driver' => Expect::anyOf('mysql')->required(),
			'host' => Expect::string()->default('localhost'),
			'port' => Expect::int()->default(3306),
		]),
		Expect::structure([
			'driver' => Expect::anyOf('sqlite')->required(),
			'path' => Expect::string()->required(),
		]),
		Expect::structure([
			'driver' => Expect::anyOf('pgsql')->required(),
			'host' => Expect::string()->default('localhost'),
			'port' => Expect::int()->default(5432),
		]),
	);

	Assert::equal(
		(object) ['driver' => 'sqlite', 'path' => '/tmp/db.sqlite'],
		(new Processor)->process($schema, ['driver' => 'sqlite', 'path' => '/tmp/db.sqlite']),
	);

	Assert::equal(
		(object) ['driver' => 'mysql', 'host' => 'localhost', 'port' => 3306],
		(new Processor)->process($schema, ['driver' => 'mysql']),
	);

	Assert::equal(
		(object) ['driver' => 'pgsql', 'host' => 'db.example.com', 'port' => 5432],
		(new Processor)->process($schema, ['driver' => 'pgsql', 'host' => 'db.example.com']),
	);
});


test('nested discriminated unions', function () {
	$schema = Expect::structure([
		'cache' => Expect::anyOf(
			Expect::structure([
				'driver' => Expect::anyOf('file')->required(),
				'dir' => Expect::string()->required(),
			]),
			Expect::structure([
				'driver' => Expect::anyOf('redis')->required(),
				'host' => Expect::string()->default('localhost'),
				'port' => Expect::int()->default(6379),
			]),
			false,
		),
	]);

	Assert::equal(
		(object) ['cache' => (object) ['driver' => 'file', 'dir' => '/tmp/cache']],
		(new Processor)->process($schema, ['cache' => ['driver' => 'file', 'dir' => '/tmp/cache']]),
	);

	Assert::equal(
		(object) ['cache' => false],
		(new Processor)->process($schema, ['cache' => false]),
	);
});
