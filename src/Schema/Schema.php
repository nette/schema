<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;


/**
 * Defines the contract for schema elements used in data validation and normalization.
 */
interface Schema
{
	/**
	 * Applies pre-processing transformations to the raw input value (e.g., via before() hooks).
	 */
	function normalize(mixed $value, Context $context): mixed;

	/**
	 * Merges two normalized values, with $value taking priority over $base. Merge errors are reported via Context.
	 */
	function merge(mixed $value, mixed $base, Context $context): mixed;

	/**
	 * Validates the value and applies defaults, transforms, and assertions.
	 */
	function complete(mixed $value, Context $context): mixed;

	/**
	 * Returns the default value, or adds a missing-item error if the field is required.
	 */
	function completeDefault(Context $context): mixed;
}
