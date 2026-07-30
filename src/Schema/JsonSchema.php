<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;

use Nette;
use function count, is_bool, is_float, is_int, is_string;


/**
 * Exports a schema as JSON Schema.
 */
final class JsonSchema
{
	/**
	 * @return array<string, mixed>|\stdClass
	 * @throws Nette\NotSupportedException  when the schema contains a class type or another PHP-only type
	 */
	public static function export(Schema $schema): array|\stdClass
	{
		return self::build($schema);
	}


	/**
	 * @param  Schema|array<string, mixed>  $schema  a schema, or a description as describe() returns it
	 * @return array<string, mixed>|\stdClass
	 */
	private static function build(Schema|array $schema): array|\stdClass
	{
		$item = self::describe($schema);
		$kind = $item['kind'];
		if (!$kind instanceof Kind) {
			throw new Nette\InvalidStateException('describe() must report a Kind.');
		}

		$res = match ($kind) {
			Kind::Any => [],
			Kind::Null => ['type' => 'null'],
			Kind::Bool => ['type' => 'boolean'],
			Kind::Int => ['type' => 'integer'] + self::range($item, 'minimum', 'maximum'),
			Kind::Float, Kind::Number => ['type' => 'number'] + self::range($item, 'minimum', 'maximum'),
			Kind::String => self::buildString($item),
			// an iterable of items is narrowed to a JSON array, which PHP accepts too
			Kind::List, Kind::Iterable => ['type' => 'array', 'items' => self::buildOrAny($item['items'])] + self::range($item, 'minItems', 'maxItems'),
			Kind::Array => self::buildArray($item),
			Kind::Structure => self::buildStructure($item),
			Kind::Enum => self::buildEnum($item['values']),
			Kind::Union => self::buildUnion($item),
			Kind::Instance, Kind::Other, Kind::Object, Kind::Callable => throw new Nette\NotSupportedException("Type '" . ($item['type'] ?? strtolower($kind->name)) . "' cannot be expressed in JSON Schema."),
		};

		if ($item['nullable'] ?? false) {
			if (isset($res['enum'])) {
				$res['enum'][] = null;
			}
			if (isset($res['type'])) {
				$res['type'] = [$res['type'], 'null'];
			} elseif (isset($res['anyOf'])) {
				$res['anyOf'][] = ['type' => 'null'];
			}
		}

		if (($item['description'] ?? null) !== null) {
			$res['description'] = $item['description'];
		}

		return $res ?: new \stdClass;
	}


	/**
	 * @param  Schema|array<string, mixed>  $schema
	 * @return array<string, mixed>
	 */
	private static function describe(Schema|array $schema): array
	{
		if (!$schema instanceof Schema) {
			return $schema;
		} elseif (
			$schema instanceof Elements\Type
			|| $schema instanceof Elements\Structure
			|| $schema instanceof Elements\AnyOf
		) {
			return $schema->describe();
		}
		throw new Nette\NotSupportedException('Element ' . $schema::class . ' cannot be expressed in JSON Schema.');
	}


	/**
	 * @param  Schema|array<string, mixed>|null  $schema
	 * @return array<string, mixed>|\stdClass
	 */
	private static function buildOrAny(Schema|array|null $schema): array|\stdClass
	{
		return $schema === null ? new \stdClass : self::build($schema);
	}


	/**
	 * @param  array<string, mixed>  $item
	 * @return array<string, mixed>
	 */
	private static function buildString(array $item): array
	{
		$res = ['type' => 'string'] + self::range($item, 'minLength', 'maxLength');
		if ($item['pattern'] !== null) {
			$res['pattern'] = '^(?:' . $item['pattern'] . ')$';
		}
		if ($item['format'] !== null) {
			$res['format'] = $item['format'];
		}
		return $res;
	}


	/**
	 * A PHP array is a JSON array when its keys are integers and an object otherwise.
	 * @param  array<string, mixed>  $item
	 * @return array<string, mixed>
	 */
	private static function buildArray(array $item): array
	{
		$keys = $item['keys'] === null ? null : self::describe($item['keys']);
		if ($keys && $keys['kind'] === Kind::Int) {
			return ['type' => 'array', 'items' => self::buildOrAny($item['items'])]
				+ self::range($item, 'minItems', 'maxItems');
		} elseif ($item['items'] === null && $keys === null) {
			throw new Nette\NotSupportedException('An array without item type cannot be expressed in JSON Schema; use listOf(), arrayOf() or structure().');
		}

		$res = ['type' => 'object', 'additionalProperties' => self::buildOrAny($item['items'])]
			+ self::range($item, 'minProperties', 'maxProperties');
		if ($keys && ($keys['pattern'] ?? null) !== null) {
			$res['propertyNames'] = ['pattern' => '^(?:' . $keys['pattern'] . ')$'];
		}
		return $res;
	}


	/**
	 * @param  array<string, mixed>  $item
	 * @return array<string, mixed>
	 */
	private static function buildStructure(array $item): array
	{
		$properties = $required = [];
		foreach ($item['shape'] as $key => $property) {
			$properties[$key] = self::build($property);
			if (self::describe($property)['required']) {
				$required[] = (string) $key;
			}
		}

		return [
			'type' => 'object',
			'properties' => $properties ?: new \stdClass,
			'required' => $required,
			'additionalProperties' => $item['otherItems'] ? self::build($item['otherItems']) : false,
		] + self::range($item, 'minProperties', 'maxProperties');
	}


	/**
	 * The type is stated only when every value shares it.
	 * @param  list<mixed>  $values
	 * @return array<string, mixed>
	 */
	private static function buildEnum(array $values): array
	{
		$types = array_unique(array_map(fn($value) => match (true) {
			is_string($value) => 'string',
			is_int($value) => 'integer',
			is_float($value) => 'number',
			is_bool($value) => 'boolean',
			default => null,
		}, $values));
		return (count($types) === 1 && reset($types) !== null ? ['type' => reset($types)] : []) + ['enum' => $values];
	}


	/**
	 * Scalar values of an anyOf() come first as one enum, then the schema variants.
	 * @param  array<string, mixed>  $item
	 * @return array<string, mixed>
	 */
	private static function buildUnion(array $item): array
	{
		$variants = array_map(self::build(...), $item['variants']);
		if ($item['values'] ?? []) {
			array_unshift($variants, self::buildEnum($item['values']));
		}
		return ['anyOf' => $variants];
	}


	/**
	 * @param  array<string, mixed>  $item
	 * @return array<string, int|float>
	 */
	private static function range(array $item, string $minKey, string $maxKey): array
	{
		$number = fn(?float $value) => $value === null ? null : ($value == (int) $value ? (int) $value : $value);
		return array_filter([$minKey => $number($item['min']), $maxKey => $number($item['max'])], fn($v) => $v !== null);
	}
}
