<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette\Schema\Context;
use Nette\Schema\Helpers;


/**
 * An int or a float.
 */
final class NumberType extends Type
{
	/** @var array{?float, ?float} */
	private array $range = [null, null];


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


	public function describe(): array
	{
		return array_merge(parent::describe(), $this->describeRange($this->range));
	}


	protected function getExpression(bool $withRange = false): string
	{
		return parent::getExpression() . ($withRange && $this->range !== [null, null] ? ':' . implode('..', $this->range) : '');
	}


	protected function validate(mixed $value, Context $context): mixed
	{
		$isOk = $context->createChecker();
		parent::validate($value, $context);
		$isOk() && Helpers::validateRange($value, $this->range, $context);
		return $value;
	}
}
