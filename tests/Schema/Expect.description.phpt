<?php declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('description() does not affect validation', function () {
	$schema = Expect::structure([
		'name' => Expect::string()->description('Full name')->required(),
	]);

	Assert::equal((object) ['name' => 'John'], (new Processor)->process($schema, ['name' => 'John']));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ["The mandatory item 'name' is missing."]);
});
