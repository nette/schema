<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\Context;


/**
 * @internal
 */
trait Base
{
	/** @var bool */
	private $required = false;

	/** @var mixed */
	private $default;

	/** @var callable|null */
	private $before;

	/** @var array[] */
	private $asserts = [];

	/** @var string|null */
	private $castTo;

	/** @var string|null */
	private $deprecated;


	public function default($value): self
	{
		$this->default = $value;
		return $this;
	}


	public function required(bool $state = true): self
	{
		$this->required = $state;
		return $this;
	}


	public function before(callable $handler): self
	{
		$this->before = $handler;
		return $this;
	}


	public function castTo(string $type): self
	{
		$this->castTo = $type;
		return $this;
	}


	public function assert(callable $handler, string $description = null): self
	{
		$this->asserts[] = [$handler, $description];
		return $this;
	}


	/** Marks option as deprecated */
	public function deprecated(string $message = 'Option %path% is deprecated.'): self
	{
		$this->deprecated = $message;
		return $this;
	}


	public function completeDefault(Context $context)
	{
		if ($this->required) {
			$context->addError(
				'The mandatory option %path% is missing.',
				Nette\Schema\Message::OPTION_MISSING
			);
			return null;
		}
		return $this->default;
	}


	public function doNormalize($value, Context $context)
	{
		if ($this->before) {
			$value = ($this->before)($value);
		}
		return $value;
	}


	private function doDeprecation(Context $context): void
	{
		if ($this->deprecated !== null) {
			$context->addWarning(
				$this->deprecated,
				Nette\Schema\Message::DEPRECATED
			);
		}
	}


	private function doValidate($value, string $expected, Context $context): bool
	{
		if (!Nette\Utils\Validators::is($value, $expected)) {
			$expected = str_replace(['|', ':'], [' or ', ' in range '], $expected);
			$context->addError(
				'The option %path% expects to be %expected%, %value% given.',
				Nette\Schema\Message::UNEXPECTED_VALUE,
				['value' => $value, 'expected' => $expected]
			);
			return false;
		}
		return true;
	}


	private function doFinalize($value, Context $context)
	{
		if ($this->castTo) {
			if (Nette\Utils\Reflection::isBuiltinType($this->castTo)) {
				settype($value, $this->castTo);
			} else {
				$value = Nette\Utils\Arrays::toObject($value, new $this->castTo);
			}
		}

		foreach ($this->asserts as $i => [$handler, $description]) {
			if (!$handler($value)) {
				$expected = $description ?: (is_string($handler) ? "$handler()" : "#$i");
				$context->addError(
					'Failed assertion ' . ($description ? "'%assertion%'" : '%assertion%') . ' for option %path% with value %value%.',
					Nette\Schema\Message::FAILED_ASSERTION,
					['value' => $value, 'assertion' => $expected]
				);
				return;
			}
		}

		return $value;
	}
}
