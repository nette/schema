<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;

use function count, in_array;


/**
 * Translates the type-expression language of Expect::type() ('int|string', '?Foo', 'int[]', 'string:1..5',
 * 'pattern:\d+') into a plain description.
 * @internal
 */
final class TypeExpression
{
	private const Legacy = [
		'resource', 'none', 'numeric', 'numericint', 'alnum', 'alpha', 'digit', 'lower', 'upper', 'space',
		'xdigit', 'identifier', 'uri', 'url', 'class', 'interface', 'directory', 'file',
	];


	/** @return array<string, mixed> */
	public static function parse(string $expression): array
	{
		$variants = [];
		$nullable = $dynamic = false;
		foreach (explode('|', $expression) as $part) {
			if (str_starts_with($part, '?')) {
				$nullable = true;
				$part = substr($part, 1);
			}

			if ($part === 'null') {
				$nullable = true;
			} elseif ($part === DynamicParameter::class) {
				$dynamic = true;
			} else {
				array_push($variants, ...self::parseAlternative($part));
			}
		}

		$item = match (count($variants)) {
			0 => ['kind' => $nullable ? Kind::Null : Kind::Any],
			1 => $variants[0],
			default => ['kind' => Kind::Union, 'variants' => $variants],
		};
		return $item + ['nullable' => $nullable && count($variants) > 0, 'dynamic' => $dynamic];
	}


	/**
	 * One alternative of the expression; 'number' and 'scalar' expand to several.
	 * @return list<array<string, mixed>>
	 */
	private static function parseAlternative(string $part): array
	{
		if (str_ends_with($part, '[]')) {
			$items = self::parseAlternative(substr($part, 0, -2));
			return [[
				'kind' => Kind::Iterable,
				'items' => count($items) === 1 ? $items[0] : ['kind' => Kind::Union, 'variants' => $items],
				'keys' => null,
				'min' => null,
				'max' => null,
			]];
		}

		$parts = explode(':', $part, 2);
		$name = $parts[0];
		$arg = $parts[1] ?? null;
		[$min, $max] = $arg === null || $name === 'pattern' ? [null, null] : self::parseRange($arg);
		$number = fn(Kind $kind) => ['kind' => $kind, 'min' => $min, 'max' => $max];
		$string = fn(?string $format = null, ?string $pattern = null) => ['kind' => Kind::String, 'min' => $min, 'max' => $max, 'pattern' => $pattern, 'format' => $format];
		$collection = fn(Kind $kind) => ['kind' => $kind, 'items' => null, 'keys' => null, 'min' => $min, 'max' => $max];

		return match ($name) {
			'mixed' => [['kind' => Kind::Any]],
			'bool', 'boolean' => [['kind' => Kind::Bool]],
			'object' => [['kind' => Kind::Object]],
			'callable' => [['kind' => Kind::Callable]],
			'int', 'integer' => [$number(Kind::Int)],
			'float' => [$number(Kind::Float)],
			'number' => [$number(Kind::Int), $number(Kind::Float)],
			'string', 'unicode' => [$string()],
			'email' => [$string(format: 'email')],
			'pattern' => [$string(pattern: $arg)],
			'scalar' => [['kind' => Kind::Bool], $number(Kind::Int), $number(Kind::Float), $string()],
			'array' => [$collection(Kind::Array)],
			'list' => [$collection(Kind::List)],
			'iterable' => [$collection(Kind::Iterable)],
			default => [in_array($name, self::Legacy, strict: true)
				? ['kind' => Kind::Other, 'type' => $part]
				: ['kind' => Kind::Instance, 'type' => $name]],
		};
	}


	/**
	 * Bounds written as 'min..max', '..max', 'min..' or an exact 'value'.
	 * @return array{?float, ?float}
	 */
	private static function parseRange(string $range): array
	{
		$bounds = explode('..', $range) + [1 => $range];
		return [
			$bounds[0] === '' ? null : (float) $bounds[0],
			$bounds[1] === '' ? null : (float) $bounds[1],
		];
	}
}
