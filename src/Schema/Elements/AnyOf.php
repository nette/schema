<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\Context;
use Nette\Schema\Helpers;
use Nette\Schema\Schema;


final class AnyOf implements Schema
{
	use Base;
	use Nette\SmartObject;

	/** @var array */
	private $set;


	/**
	 * @param  mixed|Schema  ...$set
	 */
	public function __construct(...$set)
	{
		$this->set = $set;
	}


	public function nullable(): self
	{
		$this->set[] = null;
		return $this;
	}


	public function dynamic(): self
	{
		$this->set[] = new Type(Nette\Schema\DynamicParameter::class);
		return $this;
	}


	/********************* processing ****************d*g**/


	public function normalize($value, Context $context)
	{
		return $this->doNormalize($value, $context);
	}


	public function merge($value, $base)
	{
		if (is_array($value) && isset($value[Helpers::PREVENT_MERGING])) {
			unset($value[Helpers::PREVENT_MERGING]);
			return $value;
		}
		return Helpers::merge($value, $base);
	}


	public function complete($value, Nette\Schema\Context $context)
	{
		$expecteds = $innerErrors = [];
		foreach ($this->set as $item) {
			if ($item instanceof Schema) {
				$dolly = new Context;
				$dolly->path = $context->path;
				$res = $item->complete($value, $dolly);
				if (!$dolly->errors) {
					return $this->doFinalize($res, $context);
				}
				foreach ($dolly->errors as $error) {
					if ($error->path !== $context->path || empty($error->expected)) {
						$innerErrors[] = $error;
					} else {
						$expecteds[] = $error->expected;
					}
				}
			} else {
				if ($item === $value) {
					return $this->doFinalize($value, $context);
				}
				$expecteds[] = Nette\Schema\ValidationException::formatValue($item);
			}
		}

		if ($innerErrors) {
			$context->errors = array_merge($context->errors, $innerErrors);
		} else {
			$expecteds = implode('|', array_unique($expecteds));
			$context->addError(
				'The option %path% expects to be %expected%, %value% given.',
				Nette\Schema\ValidationException::UNEXPECTED_VALUE,
				['value' => $value, 'expected' => $expecteds]
			);
		}
	}


	public function completeDefault(Context $context)
	{
		if ($this->required) {
			$context->addError(
				'The mandatory option %path% is missing.',
				Nette\Schema\ValidationException::OPTION_MISSING
			);
			return null;
		}
		if ($this->default instanceof Schema) {
			return $this->default->completeDefault($context);
		}
		return $this->default;
	}
}
