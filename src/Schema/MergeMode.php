<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema;


enum MergeMode: int
{
	/** Replaces all items with the last one. */
	case Replace = 0;

	/** Overwrites existing keys. */
	case OverwriteKeys = 1;

	/** Overwrites existing keys and appends new indexed elements. */
	case AppendKeys = 2;
}
