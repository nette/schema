<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\Context;
use Nette\Schema\Helpers;
use Nette\Schema\Schema;


/**
 * An array, a list or an iterable, optionally with a schema for its items and keys.
 */
final class ArrayType extends Type
{
	private ?Schema $items = null;
	private ?Schema $keys = null;

	/** @var array{?float, ?float} */
	private array $range = [null, null];
	private bool $mergeDefaults = false;
	private ?Nette\Schema\MergeMode $mergeMode = null;


	public function __construct(string $type)
	{
		parent::__construct($type);
		$this->default([]);
	}


	public function min(?float $min): static
	{
		$this->range[0] = $min;
		return $this;
	}


	public function max(?float $max): static
	{
		$this->range[1] = $max;
		return $this;
	}


	/**
	 * Sets the schema of items and optionally of keys.
	 */
	public function items(string|Schema $valueType = 'mixed', string|Schema|null $keyType = null): static
	{
		$this->items = $valueType instanceof Schema ? $valueType : Nette\Schema\Expect::type($valueType);
		$this->keys = $keyType instanceof Schema || $keyType === null ? $keyType : Nette\Schema\Expect::type($keyType);
		return $this;
	}


	/**
	 * Sets how the array is combined when merging multiple configuration layers.
	 */
	public function mergeMode(Nette\Schema\MergeMode $mode): static
	{
		$this->mergeMode = $mode;
		return $this;
	}


	#[\Deprecated('mergeDefaults is disabled by default')]
	public function mergeDefaults(bool $state = true): static
	{
		if ($state === true) {
			trigger_error(__METHOD__ . '() is deprecated and will be removed in the next major version.', E_USER_DEPRECATED);
		}
		$this->mergeDefaults = $state;
		return $this;
	}


	public function describe(): array
	{
		return array_merge(
			parent::describe(),
			$this->describeRange($this->range),
			$this->items === null ? [] : ['items' => $this->items, 'keys' => $this->keys],
		);
	}


	protected function getExpression(bool $withRange = false): string
	{
		return parent::getExpression() . ($withRange && $this->range !== [null, null] ? ':' . implode('..', $this->range) : '');
	}


	/********************* processing ****************d*g**/


	protected function normalizeValue(mixed $value, Context $context): mixed
	{
		if (!is_array($value) || !$this->items) {
			return $value;
		}

		$res = [];
		foreach ($value as $key => $val) {
			$context->path[] = $key;
			$context->isKey = true;
			$key = $this->keys ? $this->keys->normalize($key, $context) : $key;
			$context->isKey = false;
			$res[$key] = $this->items->normalize($val, $context);
			array_pop($context->path);
		}

		return $res;
	}


	protected function mergeValues(mixed $value, mixed $base, Context $context): mixed
	{
		if ($this->mergeMode === Nette\Schema\MergeMode::Replace) {
			return $value;
		}

		if (is_array($value) && is_array($base)) {
			$index = $this->mergeMode === Nette\Schema\MergeMode::OverwriteKeys ? null : 0;
			foreach ($value as $key => $val) {
				if ($key === $index) {
					$base[] = $val;
					$index++;
				} elseif (array_key_exists($key, $base)) {
					$context->path[] = $key;
					$base[$key] = $this->mergeItem($val, $base[$key], $context);
					array_pop($context->path);
				} else {
					$base[$key] = $val;
				}
			}

			return $base;
		}

		return $value === null && is_array($base) ? $base : $value;
	}


	protected function mergeItem(mixed $value, mixed $base, Context $context): mixed
	{
		if ($this->items) {
			return $this->items->merge($value, $base, $context);
		}

		if (is_array($value) && is_array($base) && $this->mergeMode === null) {
			$context->addError(
				'Cannot merge %path%: the schema does not describe array items, use arrayOf() or mergeMode().',
				Nette\Schema\Message::CannotMerge,
			);
		}
		return $value;
	}


	protected function coerce(mixed $value): mixed
	{
		return $value === null && !$this->getParsed()['nullable']
			? [] // is unable to distinguish null from array in NEON
			: $value;
	}


	protected function validate(mixed $value, Context $context): mixed
	{
		$isOk = $context->createChecker();
		parent::validate($value, $context);
		$isOk() && is_array($value) && Helpers::validateRange($value, $this->range, $context);
		// items of a Traversable cannot be replaced by their completed form
		$isOk() && is_array($value) && $this->items !== null && $value = $this->completeItems($value, $this->items, $context);
		return $value;
	}


	/**
	 * @param  array<mixed>  $value
	 * @return array<mixed>
	 */
	private function completeItems(array $value, Schema $items, Context $context): array
	{
		$res = [];
		foreach ($value as $key => $val) {
			$context->path[] = $key;
			$context->isKey = true;
			$isKeyOk = $context->createChecker();
			$key = $this->keys ? $this->keys->complete($key, $context) : $key;
			$context->isKey = false;
			$keyOk = $isKeyOk();
			$val = $items->complete($val, $context);
			if ($keyOk) {
				$res[$key] = $val;
			}

			array_pop($context->path);
		}

		return $res;
	}


	protected function mergeDefault(mixed $value): mixed
	{
		return $this->mergeDefaults ? parent::mergeDefault($value) : $value;
	}
}
