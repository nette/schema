<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema;

use Nette;
use Nette\Schema\Elements\AnyOf;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;


/**
 * Schema generator.
 *
 * @method static Type scalar($default = null)
 * @method static Type string($default = null)
 * @method static Type int($default = null)
 * @method static Type float($default = null)
 * @method static Type bool($default = null)
 * @method static Type null()
 * @method static Type array($default = [])
 * @method static Type list($default = [])
 * @method static Type mixed($default = null)
 * @method static Type email($default = null)
 * @method static Type unicode($default = null)
 */
final class Expect
{
	public static function __callStatic(string $name, array $args): Type
	{
		$type = new Type($name);
		if ($args) {
			$type->default($args[0]);
		}

		return $type;
	}


	public static function type(string $type): Type
	{
		return new Type($type);
	}


	public static function anyOf(mixed ...$set): AnyOf
	{
		return new AnyOf(...$set);
	}


	/**
	 * @param  Schema[]  $items
	 */
	public static function structure(array $items): Structure
	{
		return new Structure($items);
	}


	public static function from(object|string $object, array $items = []): Structure
	{
		$ro = new \ReflectionClass($object);
		$props = $ro->hasMethod('__construct')
			? $ro->getMethod('__construct')->getParameters()
			: $ro->getProperties();

		foreach ($props as $prop) {
			\assert($prop instanceof \ReflectionProperty || $prop instanceof \ReflectionParameter);
			if ($item = &$items[$prop->getName()]) {
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
		}

		return (new Structure($items))->castTo($ro->getName());
	}


	public static function arrayOf(string|Schema $valueType, string|Schema $keyType = null): Type
	{
		return (new Type('array'))->items($valueType, $keyType);
	}


	public static function listOf(string|Schema $type): Type
	{
		return (new Type('list'))->items($type);
	}
}
