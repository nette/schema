<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('built-in', function () {
	$schema = Expect::int()->castTo('string');
	Assert::same('10', (new Processor)->process($schema, 10));

	$schema = Expect::string()->castTo('array');
	Assert::same(['foo'], (new Processor)->process($schema, 'foo'));
});


test('stdClass', function () {
	$schema = Expect::array()->castTo('stdClass');
	Assert::equal(
		(object) ['a' => 1, 'b' => 2],
		(new Processor)->process($schema, ['a' => 1, 'b' => 2]),
	);
});


test('DateTime', function () {
	$schema = Expect::array()->castTo('DateTime');
	Assert::equal(
		new DateTime('2021-01-01'),
		(new Processor)->process($schema, ['datetime' => '2021-01-01']),
	);

	$schema = Expect::string()->castTo('DateTime');
	Assert::equal(
		new DateTime('2021-01-01'),
		(new Processor)->process($schema, '2021-01-01'),
	);
});
