<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('SameDate', function () {
	$schema = Expect::Date('Y-m-d');

	Assert::same(\DateTimeImmutable::createFromFormat('Y-m-d', '2021-05-28')->format('Y-m-d'),
		(new Processor)->process($schema, '2021-05-28')->format('Y-m-d'));
});

test('NotSameDate', function () {
	$schema = Expect::Date('Y-m-d');

	Assert::notSame(\DateTimeImmutable::createFromFormat('Y-m-d', '2021-05-28')->format('Y-m-d'),
		(new Processor)->process($schema, '2021-05-29')->format('Y-m-d'));
});

test('NullableDateTime', function () {
	$schema = Expect::Date('Y-m-d')->nullable(true);

	Assert::same(null, (new Processor)->process($schema, null));
});

test('NotNullableDateTime', function () {
	$schema = Expect::Date('Y-m-d')->nullable(false);

	Assert::notSame(null, (new Processor)->process($schema, '2021-05-29')->format('Y-m-d H:i:s'));
});

test('FormatDate', function () {
	$schema = Expect::Date('Y-m-d')->format('d.m.Y')->nullable(false);

	Assert::same(\DateTimeImmutable::createFromFormat('Y-m-d', '2021-05-28')->format('d.m.Y'),
        (new Processor)->process($schema, '2021-05-28'));
});

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d')->nullable(false);

	(new Processor)->process($schema, '20212-05-29')->format('Y-m-d');
}, ["The option expects Date to match pattern 'Y-m-d', '20212-05-29' given."]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d')->nullable(false);

	(new Processor)->process($schema, null);
}, [
	"The option expects not-nullable Date, nothing given."
]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d')->nullable(false);

	(new Processor)->process($schema, false);
}, [
	"The option expects not-nullable Date, nothing given."
]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d')->nullable(false);

	(new Processor)->process($schema, '');
}, [
	"The option expects not-nullable Date, nothing given."
]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d')->nullable(false);

	(new Processor)->process($schema, 'the quick brown fox jumped over the lazy dog');
}, ["The option expects Date to match pattern 'Y-m-d', 'the quick brown fox jumped over the lazy dog' given."]);

checkValidationErrors(function () {
	$schema = Expect::DateTime('Y-m-d')->nullable(false);

	(new Nette\Schema\Processor())->process($schema, '2021-31-01');
}, ["The option expects Date to be valid Date format. Input Date is not the same as formatted date. Format:'Y-m-d' Input: '2021-31-01', Formatted: '2023-07-01'."]);
