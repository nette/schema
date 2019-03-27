<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\Context;
use Nette\Schema\DynamicParameter;
use Nette\Schema\Helpers;
use Nette\Schema\Schema;


final class Type implements Schema
{
	use Base;
	use Nette\SmartObject;

	/** @var string */
	private $type;

	/** @var Schema|null for arrays */
	private $items;

	/** @var array */
	private $range = [null, null];


	public function __construct(string $type)
	{
		static $defaults = ['list' => [], 'array' => []];
		$this->type = $type;
		$this->default = strpos($type, '[]') ? [] : $defaults[$type] ?? null;
	}


	public function nullable(): self
	{
		$this->type .= '|null';
		return $this;
	}


	public function dynamic(): self
	{
		$this->type .= '|' . DynamicParameter::class;
		return $this;
	}


	public function min(?float $min): self
	{
		$this->range[0] = $min;
		return $this;
	}


	public function max(?float $max): self
	{
		$this->range[1] = $max;
		return $this;
	}


	/**
	 * @param  string|Schema  $type
	 */
	public function items($type = 'mixed'): self
	{
		$this->items = $type instanceof Schema ? $type : new self($type);
		return $this;
	}


	/********************* processing ****************d*g**/


	public function normalize($value, Context $context)
	{
		$value = $this->doNormalize($value, $context);
		if (is_array($value) && $this->items) {
			foreach ($value as $key => $val) {
				$context->path[] = $key;
				$value[$key] = $this->items->normalize($val, $context);
				array_pop($context->path);
			}
		}
		return $value;
	}


	public function merge($value, $base)
	{
		if (is_array($value) && isset($value[Helpers::PREVENT_MERGING])) {
			unset($value[Helpers::PREVENT_MERGING]);
			return $value;
		}
		if (is_array($value) && is_array($base) && $this->items) {
			$index = 0;
			foreach ($value as $key => $val) {
				if ($key === $index) {
					$base[] = $val;
					$index++;
				} else {
					$base[$key] = array_key_exists($key, $base)
						? $this->items->merge($val, $base[$key])
						: $val;
				}
			}
			return $base;
		}

		return Helpers::merge($value, $base);
	}


	public function complete($value, Context $context)
	{
		if ($value === null && is_array($this->default)) {
			$value = []; // is unable to distinguish null from array in NEON
		}

		$expected = $this->type . ($this->range === [null, null] ? '' : ':' . implode('..', $this->range));
		if (!$this->doValidate($value, $expected, $context)) {
			return;
		}

		if ($value instanceof DynamicParameter) {
			$context->dynamics[] = [$value, str_replace('|' . DynamicParameter::class, '', $expected)];
		}

		if ($this->items) {
			$errCount = count($context->errors);
			foreach ($value as $key => $val) {
				$context->path[] = $key;
				$value[$key] = $this->items->complete($val, $context);
				array_pop($context->path);
			}
			if (count($context->errors) > $errCount) {
				return null;
			}
		}

		$value = Helpers::merge($value, $this->default);
		return $this->doFinalize($value, $context);
	}
}
