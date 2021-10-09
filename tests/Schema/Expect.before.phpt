<?php

declare(strict_types=1);

use Nette\Schema\Context;
use Nette\Schema\Expect;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$schema = Expect::array()
		->before(fn($val) => explode(',', $val));

	Assert::same(
		['1', '2', '3'],
		$schema->normalize('1,2,3', new Context),
	);
});


test('structure property', function () {
	$schema = Expect::structure([
		'key' => Expect::string()->before('strrev'),
	]);

	Assert::same(
		['key' => '3,2,1'],
		$schema->normalize(['key' => '1,2,3'], new Context),
	);
});


test('order in structure', function () {
	$schema = Expect::structure([
		'a' => Expect::string()->before(function ($val) use (&$order) {
			$order[] = 'a';
			return $val;
		}),
		'b' => Expect::string()->before(function ($val) use (&$order) {
			$order[] = 'b';
			return $val;
		}),
	])
		->otherItems(Expect::string()->before(function ($val) use (&$order) {
			$order[] = 'other';
			return $val;
		}))
		->before(function ($val) use (&$order) {
			$order[] = 'struct';
			return $val;
		});

	$order = [];
	Assert::null($schema->normalize(null, new Context));
	Assert::same(['struct'], $order);

	$order = [];
	Assert::same(['a' => 1], $schema->normalize(['a' => 1], new Context));
	Assert::same(['struct', 'a'], $order);

	$order = [];
	Assert::same(['a' => '1'], $schema->normalize(['a' => '1'], new Context));
	Assert::same(['struct', 'a'], $order);

	$order = [];
	Assert::same(['a' => 1, 'c' => 1], $schema->normalize(['a' => 1, 'c' => 1], new Context));
	Assert::same(['struct', 'a', 'other'], $order);

	$order = [];
	Assert::same(['c' => 1], $schema->normalize(['c' => 1], new Context));
	Assert::same(['struct', 'other'], $order);
});


test('order in array', function () {
	$schema = Expect::array()
		->items(Expect::string()->before(function ($val) use (&$order) {
			$order[] = 'item';
			return $val;
		}))
		->before(function ($val) use (&$order) {
			$order[] = 'array';
			return $val;
		});

	$order = [];
	Assert::null($schema->normalize(null, new Context));
	Assert::same(['array'], $order);

	$order = [];
	Assert::same(['a' => 1], $schema->normalize(['a' => 1], new Context));
	Assert::same(['array', 'item'], $order);
});
