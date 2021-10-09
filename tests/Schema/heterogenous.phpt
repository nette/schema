<?php

declare(strict_types=1);

use Nette\Schema\Context;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MySchema implements Schema
{
	public function normalize(mixed $value, Context $context): mixed
	{
		return $value * 2;
	}


	public function merge(mixed $value, mixed $base): mixed
	{
		return $base . $value;
	}


	public function complete(mixed $value, Context $context): mixed
	{
		return "'" . $value . "'";
	}


	public function completeDefault(Context $context): mixed
	{
		return 'def';
	}
}


test('', function () {
	$schema = Expect::arrayOf(new MySchema);
	$processor = new Processor;

	Assert::same([], $processor->process($schema, []));
	Assert::same(["'2'"], $processor->process($schema, [1]));
	Assert::same(["'2'", "'4'"], $processor->process($schema, [1, 2]));
});


test('', function () {
	$schema = Expect::arrayOf(new MySchema);
	$processor = new Processor;

	Assert::same([], $processor->processMultiple($schema, []));
	Assert::same([], $processor->processMultiple($schema, [[]]));
	Assert::same(["'2'"], $processor->processMultiple($schema, [[1]]));
	Assert::same(["'2'", "'4'"], $processor->processMultiple($schema, [[1], [2]]));
	Assert::same(['key' => "'24'"], $processor->processMultiple($schema, [['key' => 1], ['key' => 2]]));
});
