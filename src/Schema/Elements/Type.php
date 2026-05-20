<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\{Context, DynamicParameter, Helpers, Kind, Schema, TypeExpression};
use Nette\Utils\{Arrays, Validators};
use function count, is_array, is_bool, is_float, is_int, is_object, is_string, strlen;
use const INF;


class Type implements Schema
{
	use Base;

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
	 * Allows the value to be a DynamicParameter, which is recorded for deferred validation.
	 */
	public function dynamic(): static
	{
		$this->type = DynamicParameter::class . '|' . $this->type;
		return $this;
	}


	#[\Deprecated('bounds belong to NumberType, StringType and ArrayType')]
	public function min(int|float|null $min): static
	{
		throw new Nette\DeprecatedException("min() is not available on '$this->type', only a number, a string or an array has it; a union takes it per variant in anyOf().");
	}


	#[\Deprecated('bounds belong to NumberType, StringType and ArrayType')]
	public function max(int|float|null $max): static
	{
		throw new Nette\DeprecatedException("max() is not available on '$this->type', only a number, a string or an array has it; a union takes it per variant in anyOf().");
	}


	#[\Deprecated('a pattern belongs to StringType')]
	public function pattern(?string $pattern): static
	{
		throw new Nette\DeprecatedException("pattern() is not available on '$this->type', only a string has it; a union takes it per variant in anyOf().");
	}


	#[\Deprecated('items belong to ArrayType')]
	public function items(string|Schema $valueType = 'mixed', string|Schema|null $keyType = null): static
	{
		throw new Nette\DeprecatedException("items() is not available on '$this->type', only an array has it; a union takes it per variant in anyOf().");
	}


	#[\Deprecated('belongs to ArrayType')]
	public function mergeDefaults(bool $state = true): static
	{
		throw new Nette\DeprecatedException("mergeDefaults() is not available on '$this->type', only an array has it; a union takes it per variant in anyOf().");
	}


	/********************* inspection ****************d*g**/


	/**
	 * Reports what the type accepts as a plain array (see TypeExpression::parse()); a subclass adds its
	 * options, child schemas are reported as they are.
	 * @return array<string, mixed>
	 * @internal
	 */
	public function describe(): array
	{
		return $this->getParsed() + $this->describeBase();
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
	 * The expression as messages report it; a subclass appends its range for deferred DI validation.
	 */
	protected function getExpression(bool $withRange = false): string
	{
		return str_replace(DynamicParameter::class . '|', '', $this->type);
	}


	/**
	 * The description with the options of a subclass; a union of its kind gets them in every variant. The bounds
	 * are tightened by the deprecated range in the expression; both are checked, so the tighter one holds.
	 * @param  array{int|float|null, int|float|null}  $range
	 * @param  array<string, mixed>  $options
	 * @return array<string, mixed>
	 */
	protected function describeOptions(array $range, array $options = []): array
	{
		$narrow = fn(array $item) => [
			...$item,
			'min' => $range[0] === null ? ($item['min'] ?? null) : max($range[0], $item['min'] ?? -INF),
			'max' => $range[1] === null ? ($item['max'] ?? null) : min($range[1], $item['max'] ?? INF),
			...$options,
		];

		$item = self::describe();
		if ($item['kind'] === Kind::Union) {
			$item['variants'] = array_map($narrow, $item['variants']);
			return $item;
		}

		return $narrow($item);
	}


	/********************* processing ****************d*g**/


	public function normalize(mixed $value, Context $context): mixed
	{
		if ($prevent = (is_array($value) && isset($value[Helpers::PreventMerging]))) {
			unset($value[Helpers::PreventMerging]);
		}

		$value = $this->normalizeValue($this->doNormalize($value, $context), $context);

		if ($prevent && is_array($value)) {
			$value[Helpers::PreventMerging] = true;
		}

		return $value;
	}


	protected function normalizeValue(mixed $value, Context $context): mixed
	{
		return $value;
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


	protected function mergeValues(mixed $value, mixed $base): mixed
	{
		return Helpers::merge($value, $base);
	}


	public function complete(mixed $value, Context $context): mixed
	{
		$merge = true;
		if (is_array($value) && isset($value[Helpers::PreventMerging])) {
			unset($value[Helpers::PreventMerging]);
			$merge = false;
		}

		if ($value !== null || $context->keepNullAt !== $context->path) {
			$value = $this->coerce($value);
		}

		$this->doDeprecation($context);

		$isOk = $context->createChecker();
		$value = $this->validate($value, $context);
		$isOk() && $merge && $value !== null && $value = $this->mergeDefault($value);
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
	 * Adjusts the value before validation; arrays turn null into an empty array here.
	 */
	protected function coerce(mixed $value): mixed
	{
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
	 * Reports an error for a value that is not of the declared type and returns it,
	 * in a subclass narrowed by the options and with completed items.
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


	protected function mergeDefault(mixed $value): mixed
	{
		return Helpers::merge($value, $this->default);
	}
}
