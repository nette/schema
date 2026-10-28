<?php declare(strict_types=1);

use Nette\Schema\{Expect, Processor};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function formatDate(mixed $value): string
{
	Assert::type(DateTimeImmutable::class, $value);
	return $value->format('Y-m-d H:i:s.u P');
}


test('dateTime() accepts ISO 8601', function () {
	$schema = Expect::dateTime();
	$offset = (new DateTimeImmutable('2021-01-01'))->format('P');
	foreach ([
		'2021-01-01' => "2021-01-01 00:00:00.000000 $offset",
		'2021-01-01 10:00' => "2021-01-01 10:00:00.000000 $offset",
		'2021-01-01T10:00:30' => "2021-01-01 10:00:30.000000 $offset",
		'2021-01-01T10:00:00.123456Z' => '2021-01-01 10:00:00.123456 +00:00',
		'2021-01-01 10:00+02:00' => '2021-01-01 10:00:00.000000 +02:00',
	] as $input => $expected) {
		Assert::same($expected, formatDate((new Processor)->process($schema, $input)), $input);
	}
});


test('a DateTimeInterface is accepted as DateTimeImmutable', function () {
	$schema = Expect::dateTime();
	Assert::same(
		'2021-01-01 10:00:00.000000 +02:00',
		formatDate((new Processor)->process($schema, new DateTime('2021-01-01 10:00+02:00'))),
	);
});


test('anything else is refused', function () {
	$schema = Expect::dateTime();
	foreach (['2021-02-30', '2021-01-01 24:00', '2021-1-1', 'tomorrow', '@1700000000', "2021-01-01\n"] as $input) {
		checkValidationErrors(
			fn() => (new Processor)->process($schema, $input),
			['The item expects to be date-time (ISO 8601), ' . Nette\Schema\Helpers::formatValue($input) . ' given.'],
		);
	}

	checkValidationErrors(
		fn() => (new Processor)->process($schema, 1_700_000_000),
		['The item expects to be date-time (ISO 8601), 1700000000 given.'],
	);
	checkValidationErrors(
		fn() => (new Processor)->process($schema, null),
		['The item expects to be date-time (ISO 8601), null given.'],
	);
});


test('date() has no time', function () {
	$schema = Expect::date();
	Assert::same('2021-01-01 00:00:00', (new Processor)->process($schema, '2021-01-01')->format('Y-m-d H:i:s'));
	Assert::same(
		'2021-01-01 00:00:00',
		(new Processor)->process($schema, new DateTimeImmutable('2021-01-01 10:00'))->format('Y-m-d H:i:s'),
	);

	checkValidationErrors(
		fn() => (new Processor)->process($schema, '2021-01-01 10:00'),
		["The item expects to be date (Y-m-d), '2021-01-01...' given."],
	);
});


test('custom format', function () {
	$schema = Expect::dateTime('d.m.Y H:i');
	Assert::same('2021-02-28 10:30:00', (new Processor)->process($schema, '28.02.2021 10:30')->format('Y-m-d H:i:s'));

	foreach (['30.02.2021 10:30', '28.02.2021 25:30', '28.02.2021', '2021-02-28 10:30'] as $input) {
		checkValidationErrors(
			fn() => (new Processor)->process($schema, $input),
			['The item expects to be date-time (d.m.Y H:i), ' . Nette\Schema\Helpers::formatValue($input) . ' given.'],
		);
	}

	// the time is not taken from the moment of processing
	Assert::same('2021-02-28 00:00:00', (new Processor)->process(Expect::dateTime('d.m.Y'), '28.02.2021')->format('Y-m-d H:i:s'));
	Assert::same('2021-02-28', (new Processor)->process(Expect::date('d.m.Y'), '28.02.2021')->format('Y-m-d'));
});


test('nullable', function () {
	$schema = Expect::date()->nullable();
	Assert::null((new Processor)->process($schema, null));
	checkValidationErrors(
		fn() => (new Processor)->process($schema, 'x'),
		["The item expects to be null or date (Y-m-d), 'x' given."],
	);
});


test('min() and max()', function () {
	$schema = Expect::date()->min('2021-01-01')->max(new DateTimeImmutable('2021-12-31 23:00'));
	Assert::same('2021-12-31', (new Processor)->process($schema, '2021-12-31')->format('Y-m-d'));

	checkValidationErrors(
		fn() => (new Processor)->process($schema, '2020-12-31'),
		['The item expects to be in range 2021-01-01..2021-12-31, 2020-12-31 given.'],
	);

	// the zone is shown, as the values may come in different zones
	$schema = Expect::dateTime()->max('2021-01-01T10:00:00+01:00');
	checkValidationErrors(
		fn() => (new Processor)->process($schema, '2021-01-01T09:30:00Z'),
		['The item expects to be in range ..2021-01-01 10:00:00 +01:00, 2021-01-01 09:30:00 +00:00 given.'],
	);

	Assert::exception(
		fn() => Expect::date()->min('2021-02-30'),
		Nette\InvalidArgumentException::class,
		"Value '2021-02-30' is not a valid date (Y-m-d).",
	);
	Assert::exception(
		fn() => Expect::date()->max(5),
		Nette\InvalidArgumentException::class,
		'Value 5 is not a valid date (Y-m-d).',
	);
});


test('the default is converted when the schema is built', function () {
	$schema = Expect::structure(['since' => Expect::date()->default('2020-01-01')]);
	Assert::same('2020-01-01', (new Processor)->process($schema, [])->since->format('Y-m-d'));

	Assert::exception(
		fn() => Expect::date()->default('yesterday'),
		Nette\InvalidArgumentException::class,
		"Value 'yesterday' is not a valid date (Y-m-d).",
	);
});


test('layers replace each other', function () {
	$schema = Expect::structure(['since' => Expect::date()]);
	Assert::same(
		'2022-01-01',
		(new Processor)->processMultiple($schema, [['since' => '2021-01-01'], ['since' => '2022-01-01']])->since->format('Y-m-d'),
	);
});


test('transform() gets the converted value', function () {
	$schema = Expect::dateTime()->transform(fn(DateTimeImmutable $date) => Nette\Utils\DateTime::from($date));
	Assert::type(Nette\Utils\DateTime::class, (new Processor)->process($schema, '2021-01-01 10:00'));
});
