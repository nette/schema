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
final class Context
{
	use Nette\SmartObject;

	/** @var bool */
	public $skipDefaults = false;

	/** @var string[] */
	public $path = [];

	/** @var \stdClass[] */
	public $errors = [];

	/** @var array[] */
	public $dynamics = [];


	public function addError($message, $hint = null)
	{
		$this->errors[] = (object) ['message' => $message, 'path' => $this->path, 'hint' => $hint];
	}
}
