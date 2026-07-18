<?php declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\MergeMode;
use Nette\Schema\Processor;
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


test('ambiguous merge of untyped arrays reports error', function () {
	$schema = Expect::structure([
		'a' => Expect::array(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->processMultiple($schema, [
			['a' => ['x' => ['deep' => 1], 'y' => 2]],
			['a' => ['x' => ['deep2' => 3]]],
		]);
	}, ["Cannot merge 'a\u{a0}›\u{a0}x': the schema does not describe array items, use arrayOf() or mergeMode()."]);
});


test('explicit mergeMode() opts out of the ambiguity error', function () {
	$schema = Expect::structure([
		'a' => Expect::array()->mergeMode(MergeMode::AppendKeys),
	]);

	Assert::equal(
		(object) ['a' => ['x' => ['deep2' => 3], 'y' => 2]],
		(new Processor)->processMultiple($schema, [
			['a' => ['x' => ['deep' => 1], 'y' => 2]],
			['a' => ['x' => ['deep2' => 3]]],
		]),
	);
});


test('items schema drives deep merging', function () {
	$schema = Expect::arrayOf(Expect::array());

	Assert::equal(
		['x' => ['deep' => 1, 'deep2' => 3], 'y' => [2]],
		(new Processor)->processMultiple($schema, [
			['x' => ['deep' => 1], 'y' => [2]],
			['x' => ['deep2' => 3]],
		]),
	);
});


test('scalar collisions in untyped arrays overwrite silently', function () {
	$schema = Expect::structure([
		'a' => Expect::array(),
	]);

	Assert::equal(
		(object) ['a' => ['k' => 9, 'j' => 2]],
		(new Processor)->processMultiple($schema, [
			['a' => ['k' => 1, 'j' => 2]],
			['a' => ['k' => 9]],
		]),
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


test('mergeWith() as deep-merge escape hatch', function () {
	$deep = function ($value, $base) use (&$deep) {
		if (is_array($value) && is_array($base)) {
			foreach ($value as $k => $v) {
				$base[$k] = array_key_exists($k, $base) ? $deep($v, $base[$k]) : $v;
			}
			return $base;
		}
		return $value;
	};

	$schema = Expect::structure([
		'a' => Expect::array()->mergeWith($deep),
	]);

	Assert::equal(
		(object) ['a' => ['x' => ['deep' => 1, 'deep2' => 3], 'y' => 2]],
		(new Processor)->processMultiple($schema, [
			['a' => ['x' => ['deep' => 1], 'y' => 2]],
			['a' => ['x' => ['deep2' => 3]]],
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


test('structure appends numeric keys only with otherItems (derived mode)', function () {
	$schema = Expect::structure([
		'key' => Expect::string(),
		0 => Expect::string(),
	])->otherItems('string');

	Assert::equal(
		(object) ['old', 'key' => 'new', 'new'],
		(new Processor)->processMultiple($schema, [
			['old', 'key' => '1'],
			['new', 'key' => 'new'],
		]),
	);
});


test('unknown structure item colliding as arrays reports error', function () {
	$schema = Expect::structure([
		'known' => Expect::string(),
	]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->processMultiple($schema, [
			['extra' => ['a' => 1]],
			['extra' => ['b' => 2]],
		]);
	}, ["Cannot merge 'extra': the schema does not describe the item, use otherItems() or mergeMode()."]);
});


test('null layer does not overwrite an array but overwrites a scalar', function () {
	$schema = Expect::structure([
		'arr' => Expect::array(),
		'scalar' => Expect::mixed(),
	]);

	Assert::equal(
		(object) ['arr' => [1], 'scalar' => null],
		(new Processor)->processMultiple($schema, [
			['arr' => [1], 'scalar' => 'a'],
			['arr' => null, 'scalar' => null],
		]),
	);
});
