<?php declare(strict_types=1);

use Nette\Schema\{Expect, JsonSchema};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('scalar types', function () {
	Assert::same(['type' => 'string'], JsonSchema::export(Expect::string()));
	Assert::same(['type' => 'integer'], JsonSchema::export(Expect::int()));
	Assert::same(['type' => 'number'], JsonSchema::export(Expect::float()));
	Assert::same(['type' => 'boolean'], JsonSchema::export(Expect::bool()));
	Assert::same(['type' => 'null'], JsonSchema::export(Expect::null()));
	Assert::equal(new stdClass, JsonSchema::export(Expect::mixed()));
});


test('nullable', function () {
	Assert::same(['type' => ['string', 'null']], JsonSchema::export(Expect::string()->nullable()));
	Assert::same(
		['anyOf' => [['type' => 'integer'], ['type' => 'string'], ['type' => 'null']]],
		JsonSchema::export(Expect::type('int|string|null')),
	);
	Assert::same(
		['type' => ['string', 'null'], 'enum' => ['a', 'b', null]],
		JsonSchema::export(Expect::anyOf('a', 'b')->nullable()),
	);
	Assert::equal(new stdClass, JsonSchema::export(Expect::mixed()->nullable()));
});


test('description is exported, default and deprecated are not', function () {
	Assert::same(
		['type' => 'integer', 'description' => 'Count'],
		JsonSchema::export(Expect::int(5)->description('Count')->deprecated()),
	);
});


test('ranges', function () {
	Assert::same(['type' => 'integer', 'minimum' => 1, 'maximum' => 5], JsonSchema::export(Expect::int()->min(1)->max(5)));
	Assert::same(['type' => 'number', 'minimum' => 0.5], JsonSchema::export(Expect::float()->min(0.5)));
	Assert::same(['type' => 'string', 'minLength' => 1, 'maxLength' => 5], JsonSchema::export(Expect::string()->min(1)->max(5)));
	Assert::same(
		['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 3],
		JsonSchema::export(Expect::listOf('string')->max(3)),
	);
	Assert::same(
		['anyOf' => [['type' => 'integer', 'minimum' => 3], ['type' => 'string', 'minLength' => 3]]],
		JsonSchema::export(Expect::anyOf(Expect::int()->min(3), Expect::string()->min(3))),
	);
});


test('pattern is anchored, format is passed on', function () {
	Assert::same(['type' => 'string', 'pattern' => '^(?:\d+)$'], JsonSchema::export(Expect::string()->pattern('\d+')));
	Assert::same(['type' => 'string', 'format' => 'email'], JsonSchema::export(Expect::email()));
	Assert::same(['type' => 'string', 'format' => 'uri'], JsonSchema::export(Expect::type('url')));
	Assert::same(['type' => 'string'], JsonSchema::export(Expect::type('identifier'))); // checked by PHP alone
});


test('lists and arrays', function () {
	Assert::same(
		['type' => 'array', 'items' => ['type' => 'integer']],
		JsonSchema::export(Expect::listOf('int')),
	);
	Assert::same(
		['anyOf' => [['type' => 'integer'], ['type' => 'array', 'items' => ['type' => 'integer']]]],
		JsonSchema::export(Expect::listOf('int', wrap: true)),
	);
	Assert::same( // a single value cannot reach the minimum
		['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 2],
		JsonSchema::export(Expect::listOf('int', wrap: true)->min(2)),
	);
	Assert::same(
		['type' => 'array', 'items' => ['type' => 'integer']],
		JsonSchema::export(Expect::type('int[]')),
	);
	Assert::same(
		['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
		JsonSchema::export(Expect::arrayOf('int')),
	);
	Assert::same(
		['type' => 'array', 'items' => ['type' => 'string']],
		JsonSchema::export(Expect::arrayOf('string', 'int')),
	);
	Assert::same(
		[
			'type' => 'object',
			'additionalProperties' => ['type' => 'string'],
			'propertyNames' => ['type' => 'string', 'pattern' => '^(?:[a-z]+)$'],
		],
		JsonSchema::export(Expect::arrayOf('string', Expect::string()->pattern('[a-z]+'))),
	);
	Assert::same(
		[
			'type' => 'object',
			'additionalProperties' => ['type' => 'integer'],
			'propertyNames' => ['type' => 'string', 'enum' => ['a', 'b']],
		],
		JsonSchema::export(Expect::arrayOf('int', Expect::anyOf('a', 'b'))),
	);
	Assert::same(
		['type' => 'object', 'additionalProperties' => ['type' => 'integer'], 'propertyNames' => ['type' => 'string', 'maxLength' => 3]],
		JsonSchema::export(Expect::arrayOf('int', Expect::string()->max(3))),
	);
	Assert::equal(
		['type' => 'object', 'additionalProperties' => new stdClass],
		JsonSchema::export(Expect::arrayOf('mixed', 'string')),
	);

	Assert::exception(
		fn() => JsonSchema::export(Expect::array()),
		Nette\NotSupportedException::class,
		'An array without item type cannot be expressed in JSON Schema; use listOf(), arrayOf() or structure().',
	);
});


test('structure', function () {
	Assert::same(
		[
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Full name'],
				'age' => ['type' => ['integer', 'null']],
				'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
			],
			'required' => ['name', 'tags'],
			'additionalProperties' => false,
		],
		JsonSchema::export(Expect::structure([
			'name' => Expect::string()->required()->description('Full name'),
			'age' => Expect::int()->nullable(),
			'tags' => Expect::listOf('string')->required(),
		])),
	);

	Assert::same(
		[
			'type' => 'object',
			'properties' => ['a' => ['type' => 'integer']],
			'required' => [],
			'additionalProperties' => ['type' => 'boolean'],
			'minProperties' => 1,
		],
		JsonSchema::export(Expect::structure(['a' => Expect::int()])->otherItems('bool')->min(1)),
	);

	Assert::equal(
		['type' => 'object', 'properties' => new stdClass, 'required' => [], 'additionalProperties' => false],
		JsonSchema::export(Expect::structure([])),
	);
});


test('nested structure and Expect::from()', function () {
	$schema = Expect::from(new class {
		public string $name;
		public ?int $age = null;
	});

	Assert::same(
		[
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
				'age' => ['type' => ['integer', 'null']],
			],
			'required' => ['name'],
			'additionalProperties' => false,
		],
		JsonSchema::export($schema),
	);
});


test('enums and unions', function () {
	Assert::same(['type' => 'string', 'enum' => ['a', 'b']], JsonSchema::export(Expect::anyOf('a', 'b')));
	Assert::same(['type' => 'integer', 'enum' => [1, 2]], JsonSchema::export(Expect::anyOf(1, 2)));
	Assert::same(['enum' => ['a', 1, true]], JsonSchema::export(Expect::anyOf('a', 1, true)));
	Assert::same(
		['anyOf' => [['type' => 'string', 'enum' => ['a']], ['type' => 'integer', 'minimum' => 1]]],
		JsonSchema::export(Expect::anyOf('a', Expect::int()->min(1))),
	);
	Assert::same(['type' => 'number', 'minimum' => 1], JsonSchema::export(Expect::type('number')->min(1)));

	// the options of a union of one kind hold for every variant
	Assert::same(
		['anyOf' => [['type' => 'integer', 'minimum' => 5], ['type' => 'number', 'minimum' => 5]]],
		JsonSchema::export(Expect::type('int|float')->min(5)),
	);
	Assert::same(
		['anyOf' => [
			['type' => 'string', 'pattern' => '^(?:x+)$', 'format' => 'email'],
			['type' => 'string', 'pattern' => '^(?:x+)$', 'format' => 'uri'],
		]],
		JsonSchema::export(Expect::type('email|url')->pattern('x+')),
	);
});


test('PHP-only types are refused', function () {
	Assert::exception(
		fn() => JsonSchema::export(Expect::type(DateTime::class)),
		Nette\NotSupportedException::class,
		"Type 'DateTime' cannot be expressed in JSON Schema.",
	);
	Assert::exception(
		fn() => JsonSchema::export(Expect::structure(['x' => Expect::type('string|DateTime')])),
		Nette\NotSupportedException::class,
		"Type 'DateTime' cannot be expressed in JSON Schema.",
	);
	Assert::exception(
		fn() => JsonSchema::export(@Expect::type('numeric')), // @ 'numeric' as a type is deprecated
		Nette\NotSupportedException::class,
		"Type 'numeric' cannot be expressed in JSON Schema.",
	);
	Assert::exception(
		fn() => JsonSchema::export(Expect::type('callable')),
		Nette\NotSupportedException::class,
		"Type 'callable' cannot be expressed in JSON Schema.",
	);
	Assert::exception(
		fn() => JsonSchema::export(Expect::type('object')),
		Nette\NotSupportedException::class,
		"Type 'object' cannot be expressed in JSON Schema.",
	);
});


test('dynamic and casts do not affect the output', function () {
	Assert::same(['type' => 'integer'], JsonSchema::export(Expect::int()->dynamic()));
	Assert::same(['type' => 'string'], JsonSchema::export(Expect::string()->castTo(DateTime::class)->assert('is_string')));
});


test('the output is valid JSON', function () {
	$json = json_encode(JsonSchema::export(Expect::structure([
		'any' => Expect::mixed(),
		'empty' => Expect::structure([]),
	])));
	Assert::same(
		'{"type":"object","properties":{"any":{},"empty":{"type":"object","properties":{},"required":[],"additionalProperties":false}},"required":[],"additionalProperties":false}',
		$json,
	);
});


test('a missing nested structure is required only when it cannot be created from its defaults', function () {
	$export = JsonSchema::export(Expect::structure([
		'optional' => Expect::structure(['a' => Expect::int(1)]),
		'withRequired' => Expect::structure(['a' => Expect::int()->required()]),
		'deep' => Expect::structure(['x' => Expect::structure(['a' => Expect::int()->required()])]),
		'sized' => Expect::structure(['a' => Expect::int(1)])->min(1),
	]));
	Assert::same(['withRequired', 'deep', 'sized'], $export['required']);
});


test('a tuple exports its size and required positions', function () {
	Assert::same(
		['type' => 'array', 'prefixItems' => [['type' => 'integer'], ['type' => 'string']], 'items' => false, 'minItems' => 1],
		JsonSchema::export(Expect::tuple([Expect::int()->required(), Expect::string()])),
	);
	Assert::same(
		[
			'type' => 'array',
			'prefixItems' => [['type' => 'integer'], ['type' => 'string']],
			'items' => ['type' => 'integer'],
			'minItems' => 2,
			'maxItems' => 4,
		],
		JsonSchema::export(Expect::tuple([Expect::int(), Expect::string()->required()])->otherItems('int')->min(1)->max(4)),
	);
});


test('the output is a valid JSON Schema document', function () {
	// prefixItems must not be empty
	Assert::same(['type' => 'array', 'items' => false], JsonSchema::export(Expect::tuple([])));

	// no duplicate type
	Assert::same(['type' => 'null'], JsonSchema::export(Expect::anyOf(null)));

	// a length is a non-negative integer
	Assert::same(['type' => 'string', 'minLength' => 2, 'maxLength' => 3], JsonSchema::export(Expect::string()->min(1.5)->max(3.5)));
	Assert::same(
		['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 0],
		JsonSchema::export(Expect::listOf('int')->min(-1)),
	);

	// properties is an object even for integer keys
	Assert::same(
		'{"type":"object","properties":{"0":{"type":"integer"}},"required":[],"additionalProperties":false}',
		json_encode(JsonSchema::export(Expect::structure([Expect::int()]))),
	);
});


test('enum of non-scalar values has no type', function () {
	Assert::same(['enum' => [[1, 2], 'x']], JsonSchema::export(Expect::anyOf([1, 2], 'x')));
});
