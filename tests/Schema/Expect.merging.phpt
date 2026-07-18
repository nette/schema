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


test('removed _prevent_merging key is rejected loudly', function () {
	$schema = Expect::array();

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, ['_prevent_merging' => true, 'a' => 1]);
	}, ["The key '_prevent_merging' is no longer supported, use mergeMode() instead."]);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->processMultiple($schema, [
			['a' => ['_prevent_merging' => true, 'b' => 1]],
		]);
	}, ["The key 'a\u{a0}›\u{a0}_prevent_merging' is no longer supported, use mergeMode() instead."]);

	checkValidationErrors(function () {
		$schema = Expect::structure(['a' => Expect::array()]);
		(new Processor)->processMultiple($schema, [
			(object) ['a' => ['_prevent_merging' => true, 'x' => 1]],
		]);
	}, ["The key 'a\u{a0}›\u{a0}_prevent_merging' is no longer supported, use mergeMode() instead."]);
});


test('layers matching the same anyOf alternative merge by it (nette/database#223)', function () {
	$connection = Expect::structure([
		'dsn' => Expect::string()->required(),
		'user' => Expect::string(),
		'options' => Expect::array(),
	]);
	$schema = Expect::anyOf($connection, Expect::arrayOf($connection));

	Assert::equal(
		(object) ['options' => ['lazy' => true], 'dsn' => 'sqlite:', 'user' => 'x'],
		(new Processor)->processMultiple($schema, [
			['options' => ['lazy' => true]],
			['dsn' => 'sqlite:', 'user' => 'x'],
		]),
	);
});


test('anyOf layers matching different alternatives cannot merge arrays', function () {
	$schema = Expect::anyOf(
		Expect::structure(['a' => Expect::string()]),
		Expect::listOf('string'),
	);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->processMultiple($schema, [
			['a' => 'x'],
			['s1', 's2'],
		]);
	}, ['Cannot merge: layers do not match the same alternative.']);
});


test('scalar anyOf layer replaces array and vice versa (proxy case)', function () {
	$schema = Expect::anyOf(Expect::string(), Expect::arrayOf('string'));

	Assert::same(['5.6.7.8'], (new Processor)->processMultiple($schema, ['1.2.3.4', ['5.6.7.8']]));
	Assert::same('1.2.3.4', (new Processor)->processMultiple($schema, [['5.6.7.8'], '1.2.3.4']));
	Assert::same(['a', 'b'], (new Processor)->processMultiple($schema, [['a'], ['b']]));
});


test('explicit Replace and mergeWith() on anyOf', function () {
	$schema = Expect::anyOf(Expect::arrayOf('string'), Expect::bool())
		->mergeMode(MergeMode::Replace);

	Assert::same(['c'], (new Processor)->processMultiple($schema, [['a', 'b'], ['c']]));

	$schema = Expect::anyOf(Expect::int(), Expect::bool())
		->mergeWith(fn($value, $base) => $value + $base);

	Assert::same(5, (new Processor)->processMultiple($schema, [2, 3]));
});


test('null layer in anyOf follows the null rule', function () {
	$schema = Expect::anyOf(Expect::arrayOf('string'), Expect::string())->nullable();

	Assert::same(['a'], (new Processor)->processMultiple($schema, [['a'], null]));

	// no array variant, otherwise findAlternative would coerce the merged null to []
	$schema = Expect::anyOf(Expect::string(), Expect::bool())->nullable();

	Assert::same(null, (new Processor)->processMultiple($schema, ['s', null]));
});


test('variant reshaping in before() merges as replace (documented limitation)', function () {
	$variant = Expect::arrayOf('string')
		->before(fn($v) => is_string($v) ? explode(',', $v) : $v);
	$schema = Expect::anyOf($variant, Expect::bool());

	Assert::same(['c', 'd'], (new Processor)->processMultiple($schema, ['a,b', 'c,d']));
});
