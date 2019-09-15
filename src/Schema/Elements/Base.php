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


	public function completeDefault(Context $context)
	{
		if ($this->required) {
			$context->addError('The mandatory option %path% is missing.');
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


	private function doValidate($value, string $expected, Context $context): bool
	{
		try {
			Nette\Utils\Validators::assert($value, $expected, 'option %path%');
			return true;
		} catch (Nette\Utils\AssertionException $e) {
			$context->addError($e->getMessage(), $expected);
			return false;
		}
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
				$expected = $description ? ('"' . $description . '"') : (is_string($handler) ? "$handler()" : "#$i");
				$context->addError("Failed assertion $expected for option %path% with value " . static::formatValue($value) . '.');
				return;
			}
		}

		return $value;
	}


	private static function formatValue($value): string
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
