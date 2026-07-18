<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette\Schema\Context;
use Nette\Schema\DynamicParameter;
use Nette\Schema\Helpers;
use Nette\Schema\Kind;
use Nette\Schema\Schema;
use Nette\Schema\TypeExpression;
use Nette\Utils\Validators;
use function array_key_exists, array_map, array_pop, implode, in_array, is_array, max, min, str_replace, strpos;


final class Type implements Schema
{
	use Base;

	private string $type;
	private ?Schema $itemsValue = null;
	private ?Schema $itemsKey = null;

	/** @var array{?float, ?float} */
	private array $range = [null, null];
	private ?string $pattern = null;
	private bool $merge = true;


	public function __construct(string $type)
	{
		$defaults = ['list' => [], 'array' => []];
		$this->type = $type;
		$this->default = strpos($type, '[]') ? [] : $defaults[$type] ?? null;
	}


	/**
	 * Allows the value to be null in addition to the declared type.
	 */
	public function nullable(): self
	{
		$this->type = 'null|' . $this->type;
		return $this;
	}


	/**
	 * Controls whether the default value is merged with the input array (enabled by default).
	 */
	public function mergeDefaults(bool $state = true): self
	{
		$this->merge = $state;
		return $this;
	}


	/**
	 * Allows the value to be a DynamicParameter, which is recorded for deferred validation.
	 */
	public function dynamic(): self
	{
		$this->type = DynamicParameter::class . '|' . $this->type;
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
	 * @internal  use arrayOf() or listOf()
	 */
	public function items(string|Schema $valueType = 'mixed', string|Schema|null $keyType = null): self
	{
		$this->itemsValue = $valueType instanceof Schema
			? $valueType
			: new self($valueType);
		$this->itemsKey = $keyType instanceof Schema || $keyType === null
			? $keyType
			: new self($keyType);
		return $this;
	}


	/**
	 * Sets a regex pattern the string value must match entirely (anchored to start and end).
	 */
	public function pattern(?string $pattern): self
	{
		$this->pattern = $pattern;
		return $this;
	}


	/********************* inspection ****************d*g**/


	/**
	 * Reports what the type accepts as a plain array (see TypeExpression::parse()); min(), max(), pattern()
	 * and items() narrow every variant they apply to, child schemas are reported as they are.
	 * @return array<string, mixed>
	 */
	public function describe(): array
	{
		$narrow = function (array $item) use (&$narrow): array {
			if ($item['kind'] === Kind::Union) {
				$item['variants'] = array_map($narrow, $item['variants']);
				return $item;
			}

			$arrayLike = in_array($item['kind'], [Kind::Array, Kind::List, Kind::Iterable], strict: true);
			if ($arrayLike || in_array($item['kind'], [Kind::Int, Kind::Float, Kind::String], strict: true)) {
				$item['min'] = $this->range[0] === null ? $item['min'] : max($this->range[0], $item['min'] ?? -INF);
				$item['max'] = $this->range[1] === null ? $item['max'] : min($this->range[1], $item['max'] ?? INF);
			}
			if ($item['kind'] === Kind::String && $this->pattern !== null) {
				$item['pattern'] = $this->pattern;
			}
			if ($arrayLike && $this->itemsValue) {
				$item['items'] = $this->itemsValue;
				$item['keys'] = $this->itemsKey;
			}
			return $item;
		};

		return $narrow(TypeExpression::parse($this->type)) + $this->describeBase();
	}


	/********************* processing ****************d*g**/


	public function normalize(mixed $value, Context $context): mixed
	{
		if ($prevent = (is_array($value) && isset($value[Helpers::PreventMerging]))) {
			unset($value[Helpers::PreventMerging]);
		}

		$value = $this->doNormalize($value, $context);
		if (is_array($value) && $this->itemsValue) {
			$res = [];
			foreach ($value as $key => $val) {
				$context->path[] = $key;
				$context->isKey = true;
				$key = $this->itemsKey
					? $this->itemsKey->normalize($key, $context)
					: $key;
				$context->isKey = false;
				$res[$key] = $this->itemsValue->normalize($val, $context);
				array_pop($context->path);
			}

			$value = $res;
		}

		if ($prevent && is_array($value)) {
			$value[Helpers::PreventMerging] = true;
		}

		return $value;
	}


	public function merge(mixed $value, mixed $base, Context $context): mixed
	{
		if (is_array($value) && isset($value[Helpers::PreventMerging])) {
			unset($value[Helpers::PreventMerging]);
			return $value;
		}

		if (is_array($value) && is_array($base) && $this->itemsValue) {
			$index = 0;
			foreach ($value as $key => $val) {
				if ($key === $index) {
					$base[] = $val;
					$index++;
				} elseif (array_key_exists($key, $base)) {
					$context->path[] = $key;
					$base[$key] = $this->itemsValue->merge($val, $base[$key], $context);
					array_pop($context->path);
				} else {
					$base[$key] = $val;
				}
			}

			return $base;
		}

		return Helpers::merge($value, $base);
	}


	public function complete(mixed $value, Context $context): mixed
	{
		$merge = $this->merge;
		if (is_array($value) && isset($value[Helpers::PreventMerging])) {
			unset($value[Helpers::PreventMerging]);
			$merge = false;
		}

		if ($value === null && is_array($this->default) && !Validators::is(null, $this->type)) {
			$value = []; // is unable to distinguish null from array in NEON
		}

		$this->doDeprecation($context);

		$isOk = $context->createChecker();
		Helpers::validateType($value, $this->type, $context);
		$isOk() && Helpers::validateRange($value, $this->range, $context, $this->type);
		$isOk() && $value !== null && $this->pattern !== null && Helpers::validatePattern($value, $this->pattern, $context);
		$isOk() && is_array($value) && $this->validateItems($value, $context);
		$isOk() && $merge && $value !== null && $value = Helpers::merge($value, $this->default);
		$isOk() && $value = $this->doTransform($value, $context);
		if (!$isOk()) {
			return null;
		}

		if ($value instanceof DynamicParameter && $this->type !== DynamicParameter::class) {
			$expected = $this->type . ($this->range === [null, null] ? '' : ':' . implode('..', $this->range));
			$context->dynamics[] = [$value, str_replace(DynamicParameter::class . '|', '', $expected), $context->path];
		}
		return $value;
	}


	/** @param  array<mixed>  $value */
	private function validateItems(array &$value, Context $context): void
	{
		if (!($itemsValue = $this->itemsValue)) {
			return;
		}

		$res = [];
		foreach ($value as $key => $val) {
			$context->path[] = $key;
			$context->isKey = true;
			$isKeyOk = $context->createChecker();
			$key = $this->itemsKey ? $this->itemsKey->complete($key, $context) : $key;
			$context->isKey = false;
			$keyOk = $isKeyOk();
			$val = $itemsValue->complete($val, $context);
			if ($keyOk) {
				$res[$key] = $val;
			}

			array_pop($context->path);
		}
		$value = $res;
	}
}
