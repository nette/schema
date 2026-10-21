<?php declare(strict_types=1);

use Nette\Schema\{Expect, MergeMode, Processor};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('merge modes on arrays', function () {
	$schema = Expect::structure([
		'foo1' => Expect::array()->mergeMode(MergeMode::Replace),
		'foo2' => Expect::array()->mergeMode(MergeMode::OverwriteKeys),
		'foo3' => Expect::array()->mergeMode(MergeMode::AppendKeys),
	]);

	Assert::equal(
		(object) [
			'foo1' => ['key' => 'new'],
			'foo2' => ['new', 'key' => 'new'],
			'foo3' => ['old', 'new', 'key' => 'new'],
		],
		(new Processor)->processMultiple($schema, [
			[
				'foo1' => ['old', 'key' => '1'],
				'foo2' => ['old', 'key' => '1'],
				'foo3' => ['old', 'key' => '1'],
			],
			[
				'foo1' => ['key' => 'new'],
				'foo2' => ['new', 'key' => 'new'],
				'foo3' => ['new', 'key' => 'new'],
			],
		]),
	);
});


test('Replace mode replaces the whole value', function () {
	$schema = Expect::array()->mergeMode(MergeMode::Replace);

	Assert::same(
		[3],
		(new Processor)->processMultiple($schema, [[1, 2], [3]]),
	);
});


test('without a mode untyped arrays merge deeply, an explicit mode merges by keys only', function () {
	$layers = [
		['a' => ['x' => ['deep' => 1], 'y' => 2]],
		['a' => ['x' => ['deep2' => 3]]],
	];

	Assert::equal(
		(object) ['a' => ['x' => ['deep' => 1, 'deep2' => 3], 'y' => 2]],
		(new Processor)->processMultiple(Expect::structure(['a' => Expect::array()]), $layers),
	);

	Assert::equal(
		(object) ['a' => ['x' => ['deep2' => 3], 'y' => 2]],
		(new Processor)->processMultiple(Expect::structure(['a' => Expect::array()->mergeMode(MergeMode::AppendKeys)]), $layers),
	);
});


test('mergeWith() combines scalars', function () {
	$schema = Expect::structure([
		'debug' => Expect::bool()->mergeWith(fn($value, $base) => $value || $base),
		'level' => Expect::int()->mergeWith(fn($value, $base) => max($value, $base)),
	]);

	Assert::equal(
		(object) ['debug' => true, 'level' => 7],
		(new Processor)->processMultiple($schema, [
			['debug' => true, 'level' => 7],
			['debug' => false, 'level' => 3],
		]),
	);
});


test('merge modes on structures', function () {
	$schema = Expect::structure([
		'foo1' => Expect::structure([
			'key' => Expect::string(),
			0 => Expect::string(),
		])->mergeMode(MergeMode::Replace),
		'foo2' => Expect::structure([
			'key' => Expect::string(),
			0 => Expect::string(),
		])->mergeMode(MergeMode::OverwriteKeys),
		'foo3' => Expect::structure([
			'key' => Expect::string(),
			0 => Expect::string(),
		])->mergeMode(MergeMode::AppendKeys)->otherItems('string'),
	]);

	Assert::equal(
		(object) [
			'foo1' => (object) [null, 'key' => 'new'],
			'foo2' => (object) ['new', 'key' => 'new'],
			'foo3' => (object) ['old', 'new', 'key' => 'new'],
		],
		(new Processor)->processMultiple($schema, [
			[
				'foo1' => ['old', 'key' => '1'],
				'foo2' => ['old', 'key' => '1'],
				'foo3' => ['old', 'key' => '1'],
			],
			[
				'foo1' => ['key' => 'new'],
				'foo2' => ['new', 'key' => 'new'],
				'foo3' => ['new', 'key' => 'new'],
			],
		]),
	);
});


test('mergeWith() on anyOf and on a tuple', function () {
	$schema = Expect::anyOf(Expect::int(), Expect::bool())
		->mergeWith(fn($value, $base) => $value + $base);

	Assert::same(5, (new Processor)->processMultiple($schema, [2, 3]));

	$schema = Expect::tuple([Expect::int(), Expect::int()])
		->mergeWith(fn($value, $base) => [$value[0] + $base[0], $value[1] + $base[1]]);

	Assert::same([4, 6], (new Processor)->processMultiple($schema, [[1, 2], [3, 4]]));
});
