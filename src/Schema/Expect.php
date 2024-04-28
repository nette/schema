<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;

use Nette;
use Nette\Schema\Elements\AnyOf;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use function is_object;


/**
 * Schema generator.
 *
 * @method static Type scalar($default = null)
 * @method static Type string($default = null)
 * @method static Type int($default = null)
 * @method static Type float($default = null)
 * @method static Type bool($default = null)
 * @method static Type null()
 * @method static Type list($default = [])
 * @method static Type mixed($default = null)
 * @method static Type email($default = null)
 * @method static Type unicode($default = null)
 */
final class Expect
{
	/** @param  list<mixed>  $args */
	public static function __callStatic(string $name, array $args): Type
	{
		$type = new Type($name);
		if ($args) {
			$type->default($args[0]);
		}

		return $type;
	}


	/**
	 * Creates a schema for a custom type expression (e.g., 'int|string', 'null|float').
	 */
	public static function type(string $type): Type
	{
		return new Type($type);
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

			$item = new Type($propType = (string) (Nette\Utils\Type::fromReflection($prop) ?? 'mixed'));
			if (class_exists($propType)) {
				$item = static::from($propType);
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
				if (is_object($default)) {
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
	public static function array(?array $shape = []): Structure|Type
	{
		$shape ??= [];
		return Nette\Utils\Arrays::first($shape) instanceof Schema
			? (new Structure($shape))->castTo('array')
			: (new Type('array'))->default($shape);
	}


	/**
	 * Creates an associative or indexed array schema where every value matches the given type.
	 */
	public static function arrayOf(string|Schema $valueType, string|Schema|null $keyType = null): Type
	{
		return (new Type('array'))->items($valueType, $keyType);
	}


	/**
	 * Creates a list schema (sequentially indexed from 0) where every element matches the given type.
	 */
	public static function listOf(string|Schema $type): Type
	{
		return (new Type('list'))->items($type);
	}
}
