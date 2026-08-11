<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette\Schema\Schema;


/**
 * An array, a list or an iterable, optionally with a schema for its items and keys.
 */
final class ArrayType extends Type
{
	/**
	 * Sets the schema of items and optionally of keys.
	 */
	public function items(string|Schema $valueType = 'mixed', string|Schema|null $keyType = null): static
	{
		return parent::items($valueType, $keyType);
	}


	/** @deprecated  an array has no pattern */
	public function pattern(?string $pattern): static
	{
		trigger_error(__METHOD__ . '() is deprecated, an array has no pattern.', E_USER_DEPRECATED);
		return parent::pattern($pattern);
	}
}
