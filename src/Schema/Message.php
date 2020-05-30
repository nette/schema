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

	/** no variables */
	public const OPTION_MISSING = 'schema.optionMissing';

	/** variables: {value: string, pattern: string} */
	public const PATTERN_MISMATCH = 'schema.patternMismatch';

	/** variables: {value: mixed, expected: string} */
	public const UNEXPECTED_VALUE = 'schema.unexpectedValue';

	/** variables: {value: mixed, assertion: string} */
	public const FAILED_ASSERTION = 'schema.failedAssertion';

	/** variables: {hint: string} */
	public const UNEXPECTED_KEY = 'schema.unexpectedKey';

	/** @var string */
	public $message;

	/** @var string */
	public $code;

	/** @var string[] */
	public $path;

	/** @var string[] */
	public $variables;


	public function __construct(string $message, string $code, array $path, array $variables = [])
	{
		$this->message = $message;
		$this->code = $code;
		$this->path = $path;
		$this->variables = $variables;
	}


	public function toString(): string
	{
		$vars = $this->variables;
		$vars['path'] = $this->path ? "'" . implode(' › ', $this->path) . "'" : null;
		$vars['value'] = Helpers::formatValue($vars['value'] ?? null);

		return preg_replace_callback('~( ?)%(\w+)%~', function ($m) use ($vars) {
			[, $space, $key] = $m;
			return $vars[$key] === null ? '' : $space . $vars[$key];
		}, $this->message);
	}
}
