<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;

use Nette;
use Nette\Schema\Elements\AnyOf;
use Nette\Schema\Elements\ArrayType;
use Nette\Schema\Elements\EnumType;
use Nette\Schema\Elements\NumberType;
use Nette\Schema\Elements\StringType;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use function count, is_object, is_string;


/**
 * Schema generator.
 *
 * @method static Type scalar($default = null)
 * @method static StringType string($default = null)
 * @method static NumberType int($default = null)
 * @method static NumberType float($default = null)
 * @method static Type bool($default = null)
 * @method static Type null()
 * @method static ArrayType list($default = [])
 * @method static Type mixed($default = null)
 * @method static StringType email($default = null)
 * @method static StringType unicode($default = null)
 */
final class Expect
{
	/** @param  list<mixed>  $args */
	public static function __callStatic(string $name, array $args): Type
	{
		$type = self::type($name);
		if ($args) {
			$type->default($args[0]);
		}

		return $type;
	}


	/**
	 * Creates a schema for a type expression (e.g., 'int|string', 'null|float', 'email'); an expression
	 * of a single kind of value gets its dedicated StringType, NumberType, ArrayType or EnumType.
	 */
	public static function type(string $type): Type
	{
		$class = self::typeClass(TypeExpression::parse($type));
		return new $class($type);
	}


	/**
	 * The subclass of Type that Expect::type() returns for a parsed expression: the dedicated one when
	 * every variant is of its kind, the plain Type otherwise.
	 * @param  array<string, mixed>  $item
	 * @return class-string<Type>
	 * @internal
	 */
	public static function typeClass(array $item): string
	{
		$variants = $item['kind'] === Kind::Union ? $item['variants'] : [$item];
		$classes = array_unique(array_map(fn(array $variant) => match ($variant['kind']) {
			Kind::String => StringType::class,
			Kind::Int, Kind::Float, Kind::Number => NumberType::class,
			Kind::Array, Kind::List, Kind::Iterable => ArrayType::class,
			default => Type::class,
		}, $variants));
		return count($classes) === 1 ? reset($classes) : Type::class;
	}


	/**
	 * Creates a schema for a backed enum: a case or its backing value is accepted, the case is returned.
	 * @param  class-string<\BackedEnum>  $enum
	 */
	public static function enum(string $enum): EnumType
	{
		return new EnumType($enum);
	}


	/**
	 * Creates a union schema that accepts any of the given values or sub-schemas.
	 */
	public static function anyOf(mixed ...$set): AnyOf
	{
		return new AnyOf(...$set);
	}


	/**
	 * Creates a structure schema with defined properties; output is stdClass.
	 * @param  Schema[]  $shape
	 */
	public static function structure(array $shape): Structure
	{
		return new Structure($shape);
	}


	/**
	 * Generates a structure schema from a class by reflecting its properties or constructor parameters.
	 * @param  class-string|object  $object
	 * @param  array<string, Schema>  $items
	 */
	public static function from(object|string $object, array $items = []): Structure
	{
		$ro = new \ReflectionClass($object);
		$props = $ro->hasMethod('__construct')
			? $ro->getMethod('__construct')->getParameters()
			: $ro->getProperties();

		foreach ($props as $prop) {
			$name = $prop->getName();
			if (isset($items[$name])) {
				continue;
			}

			$propType = (string) (Nette\Utils\Type::fromReflection($prop) ?? 'mixed');
			if (is_subclass_of($enum = ltrim($propType, '?'), \BackedEnum::class)) {
				$item = self::enum($enum);
				$enum === $propType || $item->nullable();
			} elseif (class_exists($propType) && !enum_exists($propType)) {
				$item = static::from($propType);
			} else {
				$item = self::type($propType);
			}

			$hasDefault = match (true) {
				$prop instanceof \ReflectionParameter => $prop->isOptional(),
				is_object($object) => $prop->isInitialized($object),
				default => $prop->hasDefaultValue(),
			};
			if ($hasDefault) {
				$default = match (true) {
					$prop instanceof \ReflectionParameter => $prop->getDefaultValue(),
					is_object($object) => $prop->getValue($object),
					default => $prop->getDefaultValue(),
				};
				if (is_object($default) && !$default instanceof \UnitEnum) {
					$item = static::from($default);
				} else {
					$item->default($default);
				}
			} else {
				$item->required();
			}

			$items[$name] = $item;
		}

		return (new Structure($items))->castTo($ro->getName());
	}


	/**
	 * Creates an array schema. When passed Schema elements, behaves like structure() but outputs an array.
	 * Without Schema elements, creates a plain array type with the given default value.
	 * @param  mixed[]  $shape
	 */
	public static function array(?array $shape = []): Structure|ArrayType
	{
		$shape ??= [];
		return Nette\Utils\Arrays::first($shape) instanceof Schema
			? (new Structure($shape))->castTo('array')
			: (new ArrayType('array'))->default($shape);
	}


	/**
	 * Creates an associative or indexed array schema where every value matches the given type.
	 */
	public static function arrayOf(string|Schema $valueType, string|Schema|null $keyType = null): ArrayType
	{
		return (new ArrayType('array'))->items($valueType, $keyType);
	}


	/**
	 * Creates a list schema (sequentially indexed from 0) where every element matches the given type.
	 * With $wrap, a single value is also accepted and wrapped into a one-item list.
	 */
	public static function listOf(string|Schema $type, bool $wrap = false): ArrayType
	{
		$schema = (new ArrayType('list'))->items($type);
		if ($wrap) {
			$schema->before(fn($value) => is_array($value) || $value === null ? $value : [$value]);
		}

		return $schema;
	}


	/**
	 * Creates a fixed-size array where each position has its own schema; a later layer replaces the tuple wholesale.
	 * @param  Schema[]  $shape
	 */
	public static function tuple(array $shape): Elements\TupleType
	{
		return new Elements\TupleType($shape);
	}
}
