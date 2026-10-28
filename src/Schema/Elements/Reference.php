<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\{Context, Schema};


/**
 * Stands for the schema that Expect::recursive() builds around it and delegates everything to it.
 * @internal
 */
final class Reference implements Schema
{
	private ?Schema $target = null;
	private bool $completingDefault = false;


	public function setTarget(Schema $target): void
	{
		if ($target === $this) {
			throw new Nette\InvalidArgumentException('A recursive schema cannot consist of the reference alone.');
		}
		$this->target = $target;
	}


	public function getTarget(): Schema
	{
		return $this->target ?? throw new Nette\InvalidStateException('The recursive schema is not built yet.');
	}


	public function normalize(mixed $value, Context $context): mixed
	{
		return $this->getTarget()->normalize($value, $context);
	}


	public function merge(mixed $value, mixed $base, Context $context): mixed
	{
		return $this->getTarget()->merge($value, $base, $context);
	}


	public function complete(mixed $value, Context $context): mixed
	{
		return $this->getTarget()->complete($value, $context);
	}


	public function completeDefault(Context $context): mixed
	{
		if ($this->completingDefault) {
			throw new Nette\InvalidStateException('A required item refers back to its own schema, so its default would be infinite; wrap it in anyOf(null, ...) or an array.');
		}

		$this->completingDefault = true;
		try {
			return $this->getTarget()->completeDefault($context);
		} finally {
			$this->completingDefault = false;
		}
	}
}
