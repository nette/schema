<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema;

use Nette;


/**
 * @internal
 */
final class Helpers
{
	use Nette\StaticClass;

	public const PREVENT_MERGING = '_prevent_merging';


	/**
	 * Merges dataset. Left has higher priority than right one.
	 * @return array|string
	 */
	public static function merge($value, $base)
	{
		if (is_array($value) && isset($value[self::PREVENT_MERGING])) {
			unset($value[self::PREVENT_MERGING]);
			return $value;
		}

		if (is_array($value) && is_array($base)) {
			$index = 0;
			foreach ($value as $key => $val) {
				if ($key === $index) {
					$base[] = $val;
					$index++;
				} else {
					$base[$key] = static::merge($val, $base[$key] ?? null);
				}
			}

			return $base;

		} elseif ($value === null && is_array($base)) {
			return $base;

		} else {
			return $value;
		}
	}


	/**
	 * @param  mixed  $value
	 */
	public static function formatValue($value): string
	{
		if (is_object($value)) {
			return 'object ' . $value::class;
		} elseif (is_string($value)) {
			return "'" . Nette\Utils\Strings::truncate($value, 15, '...') . "'";
		} elseif (is_scalar($value)) {
			return var_export($value, true);
		} else {
			return strtolower(gettype($value));
		}
	}
}
