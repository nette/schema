<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette\Schema\{Context, Helpers};
use function is_string;


/**
 * A string, plain or in a specified format.
 */
final class StringType extends Type
{
	/** @var array{int|float|null, int|float|null} */
	private array $range = [null, null];
	private ?string $pattern = null;


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
	 * Sets a regex pattern the whole string must match (anchored to start and end).
	 */
	public function pattern(?string $pattern): static
	{
		$this->pattern = $pattern;
		return $this;
	}


	public function describe(): array
	{
		return $this->describeOptions($this->range, $this->pattern === null ? [] : ['pattern' => $this->pattern]);
	}


	protected function getExpression(bool $withRange = false): string
	{
		return parent::getExpression() . ($withRange && $this->range !== [null, null] ? ':' . implode('..', $this->range) : '');
	}


	protected function validate(mixed $value, Context $context): mixed
	{
		$isOk = $context->createChecker();
		parent::validate($value, $context);
		$isOk() && is_string($value) && Helpers::validateRange($value, $this->range, $context, $this->getExpression());
		$isOk() && is_string($value) && $this->pattern !== null && Helpers::validatePattern($value, $this->pattern, $context);
		return $value;
	}
}
