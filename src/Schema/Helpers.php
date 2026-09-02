<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;

use Nette;
use function count, in_array, is_array, is_float, is_int, is_object, is_scalar, is_string, strlen;


/**
 * @internal
 */
final class Helpers
{
	use Nette\StaticClass;

	/**
	 * Formats a value for use in error messages (e.g., 'hello', true, object stdClass).
	 */
	public static function formatValue(mixed $value): string
	{
		if ($value instanceof DynamicParameter) {
			return 'dynamic';
		} elseif (is_object($value)) {
			return 'object ' . $value::class;
		} elseif (is_string($value)) {
			return "'" . Nette\Utils\Strings::truncate($value, 15, '...') . "'";
		} elseif (is_scalar($value)) {
			return var_export($value, return: true);
		} else {
			return get_debug_type($value);
		}
	}


	/**
	 * Adds a range error to the context if the value (or its length for strings/arrays) is outside the given range.
	 * @param  array{?float, ?float}  $range
	 */
	public static function validateRange(mixed $value, array $range, Context $context, string $types = ''): void
	{
		if (is_array($value) || is_string($value)) {
			[$length, $label] = is_array($value)
				? [count($value), 'items']
				: (in_array('unicode', explode('|', $types), strict: true)
					? [Nette\Utils\Strings::length($value), 'characters']
					: [strlen($value), 'bytes']);

			if (!self::isInRange($length, $range)) {
				$context->addError(
					"The length of %label% %path% expects to be in range %expected%, %length% $label given.",
					Message::LengthOutOfRange,
					['value' => $value, 'length' => $length, 'expected' => implode('..', $range)],
				);
			}
		} elseif ((is_int($value) || is_float($value)) && !self::isInRange($value, $range)) {
			$context->addError(
				'The %label% %path% expects to be in range %expected%, %value% given.',
				Message::ValueOutOfRange,
				['value' => $value, 'expected' => implode('..', $range)],
			);
		}
	}


	/**
	 * Checks whether a value falls within the given [min, max] range (null means no bound).
	 * @param  array{?float, ?float}  $range
	 */
	public static function isInRange(mixed $value, array $range): bool
	{
		return ($range[0] === null || $value >= $range[0])
			&& ($range[1] === null || $value <= $range[1]);
	}


	/**
	 * Adds a PatternMismatch error to the context if the value does not match the pattern.
	 */
	public static function validatePattern(string $value, string $pattern, Context $context): void
	{
		if (!preg_match("\x01^(?:$pattern)$\x01Du", $value)) {
			$context->addError(
				"The %label% %path% expects to match pattern '%pattern%', %value% given.",
				Message::PatternMismatch,
				['value' => $value, 'pattern' => $pattern],
			);
		}
	}


	/**
	 * Returns a closure that casts a value to the given type (built-in, backed enum, class with constructor, or plain class).
	 * @return \Closure(mixed, Context): mixed
	 */
	public static function getCastStrategy(string $type): \Closure
	{
		if (in_array(strtolower($type), ['array', 'bool', 'boolean', 'float', 'int', 'integer', 'string', 'object', 'null'], strict: true)) {
			return static function ($value) use ($type) {
				settype($value, $type);
				return $value;
			};

		} elseif (is_subclass_of($type, \BackedEnum::class)) {
			return static function ($value, Context $context) use ($type) {
				try {
					return $type::from($value);
				} catch (\TypeError | \ValueError) {
					$context->addError(
						'The %label% %path% expects to be %expected%, %value% given.',
						Message::TypeMismatch,
						['value' => $value, 'expected' => implode('|', array_map(fn(\BackedEnum $case) => self::formatValue($case->value), $type::cases()))],
					);
					return null;
				}
			};

		} elseif (is_subclass_of($type, \UnitEnum::class)) {
			throw new Nette\InvalidStateException("Cannot cast value to pure enum $type.");
		}

		$factory = method_exists($type, '__construct')
			? static fn($value) => is_array($value) || $value instanceof \stdClass
				? new $type(...(array) $value)
				: new $type($value)
			: static fn($value) => Nette\Utils\Arrays::toObject((array) $value, new $type);

		return static function ($value) use ($factory, $type) {
			try {
				return $factory($value);
			} catch (\Error $e) {
				throw new Nette\InvalidStateException("Unable to cast value to $type: " . $e->getMessage(), 0, $e);
			}
		};
	}
}
