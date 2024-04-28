<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;

use Nette;
use Nette\Schema\Elements\{AnyOf, ArrayType, EnumType, NumberType, StringType, Structure, Type};
use function array_key_exists, count, is_object;


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
	/** @var array<string, true>  classes from() is reflecting right now */
	private static array $reflecting = [];


	/** @param  array<int|string, mixed>  $args */
	public static function __callStatic(string $name, array $args): Type
	{
		$type = self::type($name);
		if ($args) {
			$type->default(array_key_exists('default', $args) ? $args['default'] : $args[0]);
		}

		return $type;
	}


	/**
	 * Creates a schema for a type expression (e.g., 'int|string', 'null|float', 'email'); an expression
	 * of a single kind of value gets its dedicated StringType, NumberType or ArrayType. An enum class means
	 * instanceof, as any class; Expect::enum() accepts its backing values.
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
			: array_filter($ro->getProperties(\ReflectionProperty::IS_PUBLIC), fn($prop) => !$prop->isStatic());

		self::$reflecting[$ro->getName()] = true;
		try {
			$items = self::reflectItems($ro, $props, $object, $items);
		} finally {
			unset(self::$reflecting[$ro->getName()]);
		}

		return (new Structure($items))->castTo($ro->getName());
	}


	/**
	 * @param  \ReflectionClass<object>  $ro
	 * @param  array<\ReflectionParameter|\ReflectionProperty>  $props
	 * @param  array<string, Schema>  $items
	 * @return array<string, Schema>
	 */
	private static function reflectItems(\ReflectionClass $ro, array $props, object|string $object, array $items): array
	{
		foreach ($props as $prop) {
			$name = $prop->getName();
			if ($prop instanceof \ReflectionParameter && $prop->isVariadic()) {
				throw new Nette\InvalidArgumentException("Variadic parameter \$$name of {$ro->getName()}::__construct() is not supported by Expect::from().");
			} elseif (isset($items[$name])) {
				continue;
			}

			$propType = (string) (Nette\Utils\Type::fromReflection($prop) ?? 'mixed');
			if (is_subclass_of($enum = ltrim($propType, '?'), \BackedEnum::class)) {
				$item = self::enum($enum);
				$enum === $propType || $item->nullable();
			} elseif (class_exists($propType) && !enum_exists($propType)) {
				if (isset(self::$reflecting[$propType])) {
					throw new Nette\InvalidArgumentException("Property \$$name of {$ro->getName()} refers back to $propType, Expect::from() cannot map a recursive structure; give the item in \$items.");
				}
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

		return $items;
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
		return (new ArrayType('list', $wrap))->items($type);
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
