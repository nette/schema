<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\{Context, Helpers, Kind, Message, Schema};
use function count, is_array;


/**
 * A fixed-size list where each position has its own schema; a later layer replaces the tuple wholesale.
 */
final class TupleType extends Type
{
	/** @var list<Schema> */
	private array $items;

	/** the rest elements */
	private ?Schema $otherItems = null;

	/** @var array{int|float|null, int|float|null} */
	private array $range = [null, null];


	/** @param Schema[]  $shape */
	public function __construct(array $shape)
	{
		if (!array_is_list($shape)) {
			throw new Nette\InvalidArgumentException('Tuple shape must be indexed array.');
		}

		(function (Schema ...$items) {})(...$shape);
		parent::__construct('list');
		parent::default(null); // a tuple of nothing is not a value, so null is not coerced to one
		$this->items = $shape;
	}


	/**
	 * Not supported for tuples; always throws.
	 */
	public function default(mixed $value): static
	{
		throw new Nette\InvalidStateException('Tuple cannot have default value.');
	}


	public function min(int|float|null $min): static
	{
		$this->range[0] = $min;
		return $this;
	}


	public function max(int|float|null $max): static
	{
		$this->range[1] = $max;
		return $this;
	}


	/**
	 * Allows positions beyond the shape, validating them against the given type.
	 */
	public function otherItems(string|Schema $type = 'mixed'): self
	{
		$this->otherItems = $type instanceof Schema ? $type : Nette\Schema\Expect::type($type);
		return $this;
	}


	/**
	 * Appends positions to the tuple.
	 * @param  Schema[]|self  $shape
	 */
	public function extend(array|self $shape): self
	{
		$shape = $shape instanceof self ? $shape->items : $shape;
		return new self([...$this->items, ...$shape]);
	}


	/** @return list<Schema> */
	public function getShape(): array
	{
		return $this->items;
	}


	public function describe(): array
	{
		return ['kind' => Kind::Tuple]
			+ $this->describeOptions($this->range, ['shape' => $this->items, 'otherItems' => $this->otherItems]);
	}


	protected function getExpression(bool $withRange = false): string
	{
		return parent::getExpression() . ($withRange && $this->range !== [null, null] ? ':' . implode('..', $this->range) : '');
	}


	/********************* processing ****************d*g**/


	protected function normalizeValue(mixed $value, Context $context): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		Helpers::rejectPreventMerging($value, $context);
		foreach ($value as $key => $val) {
			if ($itemSchema = $this->items[$key] ?? $this->otherItems) {
				$context->path[] = $key;
				$value[$key] = $itemSchema->normalize($val, $context);
				array_pop($context->path);
			}
		}

		return $value;
	}


	protected function mergeValues(mixed $value, mixed $base, Context $context): mixed
	{
		return $value;
	}


	protected function validate(mixed $value, Context $context): mixed
	{
		$isOk = $context->createChecker();
		parent::validate($value, $context);
		$isOk() && is_array($value) && Helpers::validateRange($value, $this->range, $context);
		$isOk() && is_array($value) && $value = $this->completeItems($value, $context);
		return $value;
	}


	/**
	 * @param  array<mixed>  $value
	 * @return array<mixed>
	 */
	private function completeItems(array $value, Context $context): array
	{
		foreach ($value as $index => $val) {
			$context->path[] = $index;
			if ($itemSchema = $this->items[$index] ?? $this->otherItems) {
				$value[$index] = $itemSchema->complete($val, $context);
			} else {
				$context->addError('Unexpected item %path%.', Message::UnexpectedItem);
			}
			array_pop($context->path);
		}

		for ($index = count($value); $index < count($this->items); $index++) {
			$context->path[] = $index;
			$default = $this->items[$index]->completeDefault($context); // checks required item
			if (!$context->skipDefaults) {
				$value[] = $default;
			}
			array_pop($context->path);
		}

		return $value;
	}
}
