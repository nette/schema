<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema;

use Nette;


/**
 * Validation error.
 */
class ValidationException extends Nette\InvalidStateException
{
	/** @var \stdClass[] */
	private $errors;


	/**
	 * @param  \stdClass[]  $errors
	 */
	public function __construct(?string $message, array $errors = [])
	{
		parent::__construct($message ?: self::formatMessage($errors[0]));
		$this->errors = $errors;
	}


	/**
	 * @return string[]
	 */
	public function getMessages(): array
	{
		$messages = [];
		foreach ($this->errors as $error) {
			$messages[] = $this->formatMessage($error);
		}
		return $messages;
	}


	/**
	 * @return \stdClass[]
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}


	private function formatMessage(\stdClass $error): string
	{
		$pathStr = " '" . implode(' › ', $error->path) . "'";
		return str_replace(' %path%', $error->path ? $pathStr : '', $error->message);
	}
}
