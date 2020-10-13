<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema;

use Nette;


final class Message
{
	use Nette\SmartObject;

	/** @var string */
	public $message;

	/** @var string[] */
	public $path;

	/** @var string[] */
	public $variables;


	public function __construct(string $message, array $path, array $variables = [])
	{
		$this->message = $message;
		$this->path = $path;
		$this->variables = $variables;
	}


	public function toString(): string
	{
		$pathStr = " '" . implode(' › ', $this->path) . "'";
		return str_replace(' %path%', $this->path ? $pathStr : '', $this->message);
	}
}
