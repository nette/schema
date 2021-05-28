<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

test('SameDateTime', function () {
	$schema = Expect::DateTime('Y-m-d H:i:s');

	Assert::same(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-05-28 12:00:00')->format('Y-m-d H:i:s'),
		(new Processor)->process($schema, '2021-05-28 12:00:00')->format('Y-m-d H:i:s'));
});

test('NotSameDateTime', function () {
	$schema = Expect::DateTime('Y-m-d H:i:s');

	Assert::notSame(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-05-28 11:00:00')->format('Y-m-d H:i:s'),
		(new Processor)->process($schema, '2021-05-29 12:00:00')->format('Y-m-d H:i:s'));
});

test('NullableDateTime', function () {
	$schema = Expect::DateTime('Y-m-d H:i:s')->nullable(true);

	Assert::same(null, (new Processor)->process($schema, null));
});

test('NotNullableDateTime', function () {
	$schema = Expect::DateTime('Y-m-d H:i:s')->nullable(false);

	Assert::notSame(null, (new Processor)->process($schema, '2021-05-29 12:00:00')->format('Y-m-d H:i:s'));
});

test('FormatDateTime', function () {
	$schema = Expect::DateTime('Y-m-d H:i:s')->format('H:i:s d.m.Y')->nullable(false);

	Assert::same(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-05-28 12:00:00')->format('H:i:s d.m.Y'),
        (new Processor)->process($schema, '2021-05-28 12:00:00'));
});

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d H:i:s')->nullable(false);

	(new Processor)->process($schema, '20212-05-29 12:00:00')->format('Y-m-d H:i:s');
}, ["The option expects Date to match pattern 'Y-m-d H:i:s', '20212-05-29 12:00:00' given."]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d H:i:s')->nullable(false);

	(new Processor)->process($schema, null);
}, [
	"The option expects not-nullable Date, nothing given."
]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d H:i:s')->nullable(false);

	(new Processor)->process($schema, false);
}, [
	"The option expects not-nullable Date, nothing given."
]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d H:i:s')->nullable(false);

	(new Processor)->process($schema, '');
}, [
	"The option expects not-nullable Date, nothing given."
]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d H:i:s')->nullable(false);

	(new Processor)->process($schema, 'the quick brown fox jumped over the lazy dog');
}, ["The option expects Date to match pattern 'Y-m-d H:i:s', 'the quick brown fox jumped over the lazy dog' given."]);
