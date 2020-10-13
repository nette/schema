<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema;

use Nette;


final class Context
{
	use Nette\SmartObject;

	/** @var bool */
	public $skipDefaults = false;

	/** @var string[] */
	public $path = [];

	/** @var Message[] */
	public $errors = [];

	/** @var array[] */
	public $dynamics = [];


	public function addError($message, $expected = null): Message
	{
		return $this->errors[] = new Message($message, $this->path, ['expected' => $expected]);
	}
}
