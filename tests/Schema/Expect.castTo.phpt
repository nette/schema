<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$schema = Expect::int()->castTo('string');

	Assert::same('10', (new Processor)->process($schema, 10));
});


test('', function () {
	$schema = Expect::string()->castTo('array');

	Assert::same(['foo'], (new Processor)->process($schema, 'foo'));
});


test('', function () {
	$schema = Expect::array()->castTo('stdClass');

	Assert::equal((object) ['a' => 1, 'b' => 2], (new Processor)->process($schema, ['a' => 1, 'b' => 2]));
});
