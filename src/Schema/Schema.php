<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema;


interface Schema
{
	/**
	 * Normalization.
	 */
	function normalize(mixed $value, Context $context): mixed;

	/**
	 * Merging.
	 */
	function merge(mixed $value, mixed $base): mixed;

	/**
	 * Validation and finalization.
	 */
	function complete(mixed $value, Context $context): mixed;

	function completeDefault(Context $context): mixed;
}
