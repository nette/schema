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

	/** extra variables: [] */
	public const OPTION_MISSING = 'schema.optionMissing';

	/** extra variables: ['value' => string, 'pattern' => string] */
	public const PATTERN_MISMATCH = 'schema.patternMismatch';

	/** extra variables: ['value' => string, 'expected' => string] */
	public const UNEXPECTED_VALUE = 'schema.unexpectedValue';

	/** extra variables: ['value' => string, 'assertion' => string] */
	public const FAILED_ASSERTION = 'schema.failedAssertion';

	/** extra variables: ['value' => string, 'hint' => string] */
	public const UNEXPECTED_STRUCTURE_KEY = 'schema.structure.unexpectedKey';

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
		$error = clone $error;
		$error->path = $error->path ? "'" . implode(' › ', $error->path) . "'" : null;
		$error->value = self::formatValue($error->value ?? null);
		return preg_replace_callback('~( ?)%(\w+)%~', function ($m) use ($error) {
			[, $space, $prop] = $m;
			return $error->$prop === null ? '' : $space . $error->$prop;
		}, $error->message);
	}


	public static function formatValue($value): string
	{
		if (is_string($value)) {
			return "'$value'";
		} elseif (is_bool($value)) {
			return $value ? 'true' : 'false';
		} elseif (is_scalar($value)) {
			return (string) $value;
		} else {
			return strtolower(gettype($value));
		}
	}
}
