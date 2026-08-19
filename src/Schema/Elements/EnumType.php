<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\Kind;
use function is_int, is_string;


/**
 * A backed enum: accepts a case or its backing value and yields the case.
 */
final class EnumType extends Type
{
	/**
	 * @param  class-string<\BackedEnum>  $enum
	 */
	public function __construct(
		private string $enum,
	) {
		if (!is_subclass_of($enum, \BackedEnum::class)) {
			throw new Nette\InvalidArgumentException("'$enum' is not a backed enum.");
		}
		parent::__construct($enum);
		$this->before(function (mixed $value) use ($enum): mixed {
			try {
				return is_int($value) || is_string($value) ? $enum::tryFrom($value) ?? $value : $value;
			} catch (\TypeError) {
				return $value; // a string for an int-backed enum and vice versa
			}
		});
	}


	public function describe(): array
	{
		return [
			'kind' => Kind::Enum,
			'values' => array_map(fn(\BackedEnum $case) => $case->value, ($this->enum)::cases()),
		] + parent::describe();
	}
}
