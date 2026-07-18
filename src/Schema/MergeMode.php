<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;


/**
 * Defines the strategy for merging array values from multiple configuration layers.
 */
enum MergeMode
{
	/** Replaces the entire value with the one from the later layer. */
	case Replace;

	/** Merges by keys, numeric keys are overwritten positionally. */
	case OverwriteKeys;

	/** Merges by keys, new numeric elements are appended. */
	case AppendKeys;
}
