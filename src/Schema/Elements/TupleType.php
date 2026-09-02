<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\Kind;
use Nette\Schema\MergeMode;
use Nette\Schema\Schema;
use function array_is_list;


/**
 * A fixed-size array where each position has its own schema; a later layer replaces the tuple wholesale.
 */
final class TupleType extends Structure
{
	/** @param Schema[]  $shape */
	public function __construct(array $shape)
	{
		if (!array_is_list($shape)) {
			throw new Nette\InvalidArgumentException('Tuple shape must be indexed array.');
		}

		parent::__construct($shape);
		$this->castTo('array');
		parent::mergeMode(MergeMode::Replace);
	}


	/**
	 * Not supported for tuples; always throws.
	 */
	public function mergeMode(MergeMode $mode): self
	{
		throw new Nette\InvalidStateException('A tuple always replaces, it cannot merge.');
	}


	public function describe(): array
	{
		return ['kind' => Kind::Tuple] + parent::describe();
	}


	/**
	 * A tuple has a fixed size and its positions carry meaning, so an empty block is not a tuple of defaults.
	 */
	protected function coerce(mixed $value): mixed
	{
		return $value;
	}
}
