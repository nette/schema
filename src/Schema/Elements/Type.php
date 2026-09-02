<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\{Context, DynamicParameter, Helpers, Kind, MergeMode, Message, Schema, TypeExpression};
use Nette\Utils\{Arrays, Validators};
use function array_key_exists, count, is_array, is_bool, is_float, is_int, is_object, is_string, strlen;
use const INF;


class Type implements Schema
{
	use Base;

	private ?Schema $itemsValue = null;
	private ?Schema $itemsKey = null;

	/** @var array{int|float|null, int|float|null} */
	private array $range = [null, null];
	private ?string $pattern = null;
	private bool $merge = true;
	private ?MergeMode $mergeMode = null;


	public function __construct(
		private string $type,
	) {
		$defaults = ['list' => [], 'array' => []];
		$this->default = strpos($type, '[]') ? [] : $defaults[$type] ?? null;
		$this->deprecatedExpressions();
	}


	/**
	 * Everything Validators-coupled is deprecated and 2.1 refuses it: a range in the expression,
	 * a name that is not a type, a directly constructed Type for a kind that has its own class.
	 */
	private function deprecatedExpressions(): void
	{
		$item = $this->getParsed();
		foreach ($item['kind'] === Kind::Union ? $item['variants'] : [$item] as $variant) {
			if (($variant['min'] ?? null) !== null || ($variant['max'] ?? null) !== null) {
				trigger_error("The range in '$this->type' is deprecated and 2.1 refuses it, use min() and max().", E_USER_DEPRECATED);
			}
			if ($variant['kind'] === Kind::Other) {
				trigger_error(
					"'{$variant['type']}' is deprecated as a type and 2.1 refuses it; check the value with assert() instead.",
					E_USER_DEPRECATED,
				);
			}
		}

		if (static::class === self::class && ($class = Nette\Schema\Expect::typeClass($item)) !== self::class) {
			trigger_error(
				"'$this->type' is a $class now and 2.1 refuses a plain Type for it, create it via Expect::type().",
				E_USER_DEPRECATED,
			);
		}
	}


	/**
	 * Allows the value to be null in addition to the declared type.
	 */
	public function nullable(): static
	{
		$this->type = 'null|' . $this->type;
		return $this;
	}


	/**
	 * Controls whether the default value is merged with the input array (enabled by default).
	 */
	public function mergeDefaults(bool $state = true): static
	{
		$this->unionDeprecated('mergeDefaults');
		$this->merge = $state;
		return $this;
	}


	/** @internal */
	protected function mergeMode(MergeMode $mode): static
	{
		$this->mergeMode = $mode;
		return $this;
	}


	/**
	 * Allows the value to be a DynamicParameter, which is recorded for deferred validation.
	 */
	public function dynamic(): static
	{
		$this->type = DynamicParameter::class . '|' . $this->type;
		return $this;
	}


	public function min(int|float|null $min): static
	{
		$this->unionDeprecated('min');
		$this->range[0] = $min;
		return $this;
	}


	public function max(int|float|null $max): static
	{
		$this->unionDeprecated('max');
		$this->range[1] = $max;
		return $this;
	}


	/**
	 * @internal  use arrayOf() or listOf()
	 */
	public function items(string|Schema $valueType = 'mixed', string|Schema|null $keyType = null): static
	{
		$this->unionDeprecated('items');
		$this->itemsValue = $valueType instanceof Schema
			? $valueType
			: Nette\Schema\Expect::type($valueType);
		$this->itemsKey = $keyType instanceof Schema || $keyType === null
			? $keyType
			: Nette\Schema\Expect::type($keyType);
		return $this;
	}


	/**
	 * Sets a regex pattern the string value must match entirely (anchored to start and end).
	 */
	public function pattern(?string $pattern): static
	{
		$this->unionDeprecated('pattern');
		$this->pattern = $pattern;
		return $this;
	}


	/**
	 * A union of kinds ('int|string') becomes anyOf() in the next major and takes no options of its own;
	 * they belong to the variants: anyOf(Expect::int()->min(1), Expect::string()->min(1)).
	 */
	private function unionDeprecated(string $option): void
	{
		if (static::class === self::class && $this->getParsed()['kind'] === Kind::Union) {
			trigger_error(
				"$option() on the union '$this->type' is deprecated, give it to the variants of anyOf() instead.",
				E_USER_DEPRECATED,
			);
		}
	}


	/********************* inspection ****************d*g**/


	/**
	 * Reports what the type accepts as a plain array (see TypeExpression::parse()); min(), max(), pattern()
	 * and items() narrow every variant they apply to, child schemas are reported as they are.
	 * @return array<string, mixed>
	 * @internal
	 */
	public function describe(): array
	{
		return $this->narrow($this->getParsed()) + $this->describeBase();
	}


	/**
	 * The parsed expression: 'kind', 'nullable', 'dynamic' and the keys of the kind.
	 * @return array<string, mixed>
	 */
	protected function getParsed(): array
	{
		return TypeExpression::parse($this->type);
	}


	/**
	 * The expression as messages report it; with the range appended for deferred DI validation.
	 */
	protected function getExpression(bool $withRange = false): string
	{
		$expr = str_replace(DynamicParameter::class . '|', '', $this->type);
		return $withRange && $this->range !== [null, null]
			? $expr . ':' . implode('..', $this->range)
			: $expr;
	}


	/**
	 * Puts min(), max(), pattern() and items() into every variant of the parsed expression that has the key.
	 * @param  array<string, mixed>  $item
	 * @return array<string, mixed>
	 */
	private function narrow(array $item): array
	{
		if ($item['kind'] === Kind::Union) {
			$item['variants'] = array_map($this->narrow(...), $item['variants']);
		}
		if (array_key_exists('min', $item)) { // both bounds are checked, so the tighter one holds
			$item['min'] = $this->range[0] === null ? $item['min'] : max($this->range[0], $item['min'] ?? -INF);
			$item['max'] = $this->range[1] === null ? $item['max'] : min($this->range[1], $item['max'] ?? INF);
		}
		if (array_key_exists('pattern', $item)) {
			$item['pattern'] = $this->pattern ?? $item['pattern'];
		}
		if (array_key_exists('items', $item)) {
			$item['items'] = $this->itemsValue ?? $item['items'];
			$item['keys'] = $this->itemsKey ?? $item['keys'];
		}
		return $item;
	}


	/********************* processing ****************d*g**/


	public function normalize(mixed $value, Context $context): mixed
	{
		if ($prevent = (is_array($value) && isset($value[Helpers::PreventMerging]))) {
			unset($value[Helpers::PreventMerging]);
		}

		$value = $this->doNormalize($value, $context);
		if (is_array($value)) {
			$value = $this->normalizeItems($value, $context);
		}

		if ($prevent && is_array($value)) {
			$value[Helpers::PreventMerging] = true;
		}

		return $value;
	}


	/**
	 * @param  array<mixed>  $value
	 * @return array<mixed>
	 * @internal
	 */
	protected function normalizeItems(array $value, Context $context): array
	{
		if (!$this->itemsValue) {
			return $value;
		}

		$res = [];
		foreach ($value as $key => $val) {
			$context->path[] = $key;
			$context->isKey = true;
			$key = $this->itemsKey
				? $this->itemsKey->normalize($key, $context)
				: $key;
			if (!is_int($key) && !is_string($key)) {
				$context->addError(
					'The %label% %path% expects to be int|string, %value% given.',
					Message::TypeMismatch,
					['value' => $key, 'expected' => 'int|string'],
				);
				$context->isKey = false;
				array_pop($context->path);
				continue;
			}

			$context->isKey = false;
			$res[$key] = $this->itemsValue->normalize($val, $context);
			array_pop($context->path);
		}

		return $res;
	}


	public function merge(mixed $value, mixed $base): mixed
	{
		if (is_array($value) && isset($value[Helpers::PreventMerging])) {
			unset($value[Helpers::PreventMerging]);
			return $value;
		}

		if ($this->mergeWith) {
			return ($this->mergeWith)($value, $base);
		}

		return $this->mergeValues($value, $base);
	}


	/**
	 * Without a mode, an array without described items merges deeply.
	 * @internal
	 */
	protected function mergeValues(mixed $value, mixed $base): mixed
	{
		if ($this->mergeMode === MergeMode::Replace) {
			return $value;
		}

		if (is_array($value) && is_array($base) && ($this->itemsValue || $this->mergeMode)) {
			$index = $this->mergeMode === MergeMode::OverwriteKeys ? null : 0;
			foreach ($value as $key => $val) {
				if ($key === $index) {
					$base[] = $val;
					$index++;
				} else {
					$base[$key] = array_key_exists($key, $base) && $this->itemsValue
						? $this->itemsValue->merge($val, $base[$key])
						: $val;
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

		if (
			$value === null
			&& is_array($this->default)
			&& !$this->getParsed()['nullable']
			&& !self::matches(null, $this->getParsed())
			&& $context->keepNullAt !== $context->path
		) {
			$value = []; // is unable to distinguish null from array in NEON
		}

		$this->doDeprecation($context);

		$isOk = $context->createChecker();
		$value = $this->validate($value, $context);
		$isOk() && Helpers::validateRange($value, $this->range, $context, $this->type);
		$isOk() && $value !== null && $this->pattern !== null && Helpers::validatePattern($value, $this->pattern, $context);
		$isOk() && is_array($value) && $this->validateItems($value, $context);
		$isOk() && $merge && $value !== null && $value = Helpers::merge($value, $this->default);
		$isOk() && $value = $this->doTransform($value, $context);
		if (!$isOk()) {
			return null;
		}

		if ($value instanceof DynamicParameter && $this->type !== DynamicParameter::class) {
			$context->dynamics[] = [$value, $this->getExpression(withRange: true), $context->path];
		}
		return $value;
	}


	/**
	 * Whether the value is exempt from validation: null of a nullable type, a DynamicParameter of a dynamic one.
	 */
	private function isExempt(mixed $value): bool
	{
		$parsed = $this->getParsed();
		return ($value === null && $parsed['nullable'])
			|| ($value instanceof DynamicParameter && $parsed['dynamic']);
	}


	/**
	 * Reports an error for a value that is not of the declared type and returns it.
	 */
	protected function validate(mixed $value, Context $context): mixed
	{
		if (!$this->isExempt($value) && !self::matches($value, $this->getParsed())) {
			$this->addTypeError($value, $context);
		}
		return $value;
	}


	/**
	 * Whether the value is of the kind the parsed expression describes; the one place that knows every kind.
	 * The deprecated ranges and validator names in the expression are still honored here until they go away.
	 * @param  array<string, mixed>  $variant
	 */
	protected static function matches(mixed $value, array $variant): bool
	{
		return match ($variant['kind']) {
			Kind::Any => true,
			Kind::Null => $value === null,
			Kind::Bool => is_bool($value),
			Kind::Int => is_int($value) && self::inVariantRange($value, $variant),
			Kind::Float => is_float($value) && self::inVariantRange($value, $variant),
			Kind::Number => (is_int($value) || is_float($value)) && self::inVariantRange($value, $variant),
			Kind::String => is_string($value)
				&& ($variant['format'] === null || Validators::is($value, $variant['format']))
				&& (($variant['pattern'] ?? null) === null || preg_match("\x01^(?:{$variant['pattern']})$\x01Du", $value) === 1)
				&& self::inVariantRange($variant['format'] === 'unicode' ? Nette\Utils\Strings::length($value) : strlen($value), $variant),
			Kind::Array => is_array($value) && self::inVariantRange(count($value), $variant),
			Kind::List => is_array($value) && Arrays::isList($value) && self::inVariantRange(count($value), $variant),
			Kind::Iterable => is_iterable($value) && ($variant['items'] === null || self::everyItemMatches($value, $variant['items'])),
			Kind::Object => is_object($value),
			Kind::Callable => $value && is_callable($value, syntax_only: true),
			Kind::Instance => $value instanceof $variant['type'],
			Kind::Union => Arrays::some($variant['variants'], fn($v) => self::matches($value, $v)),
			Kind::Other => Validators::is($value, $variant['type']),
			default => false,
		};
	}


	/** @param  array<string, mixed>  $variant */
	private static function inVariantRange(mixed $value, array $variant): bool
	{
		return Helpers::isInRange($value, [$variant['min'] ?? null, $variant['max'] ?? null]);
	}


	/**
	 * @param  iterable<mixed>  $values
	 * @param  array<string, mixed>  $items
	 */
	private static function everyItemMatches(iterable $values, array $items): bool
	{
		foreach ($values as $value) {
			if (!self::matches($value, $items)) {
				return false;
			}
		}
		return true;
	}


	/**
	 * Adds the type error in the wording of the expression: 'expects to be null or int'.
	 */
	private function addTypeError(mixed $value, Context $context): void
	{
		$context->addError(
			'The %label% %path% expects to be %expected%, %value% given.',
			Nette\Schema\Message::TypeMismatch,
			['value' => $value, 'expected' => str_replace(['|', ':'], [' or ', ' in range '], $this->getExpression())],
		);
	}


	/**
	 * @param  array<mixed>  $value
	 * @internal
	 */
	protected function validateItems(array &$value, Context $context): void
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
			if ($isKeyOk() && !is_int($key) && !is_string($key)) {
				$context->addError(
					'The %label% %path% expects to be int|string, %value% given.',
					Message::TypeMismatch,
					['value' => $key, 'expected' => 'int|string'],
				);
			}

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
