<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\Context;
use Nette\Schema\Helpers;
use function count, is_string;


/**
 * @internal
 */
trait Base
{
	private bool $required = false;
	private mixed $default = null;

	/** @var list<\Closure(mixed): mixed> */
	private array $before = [];

	/** @var list<\Closure(mixed, Context): mixed> */
	private array $transforms = [];
	private ?string $deprecated = null;
	private ?string $description = null;


	public function default(mixed $value): static
	{
		$this->default = $value;
		return $this;
	}


	public function required(bool $state = true): static
	{
		$this->required = $state;
		return $this;
	}


	/**
	 * Adds a pre-normalization callback applied to the raw input value before any validation.
	 * @param  callable(mixed): mixed  $handler
	 */
	public function before(callable $handler): static
	{
		$this->before[] = $handler(...);
		return $this;
	}


	/**
	 * Casts the validated value to a built-in type or instantiates the given class.
	 */
	public function castTo(string $type): static
	{
		return $this->transform(Helpers::getCastStrategy($type));
	}


	/**
	 * Adds a post-validation transformation callback. The handler may also report errors via Context.
	 * @param  callable(mixed, Context): mixed  $handler
	 */
	public function transform(callable $handler): static
	{
		$this->transforms[] = $handler(...);
		return $this;
	}


	/**
	 * Adds a custom validation assertion; optionally describe it for error messages.
	 * @param  callable(mixed): bool  $handler
	 */
	public function assert(callable $handler, ?string $description = null): static
	{
		$expected = $description ?? (is_string($handler) ? "$handler()" : '#' . count($this->transforms));
		return $this->transform(function ($value, Context $context) use ($handler, $description, $expected) {
			if ($handler($value)) {
				return $value;
			}
			$context->addError(
				'Failed assertion ' . ($description ? "'%assertion%'" : '%assertion%') . ' for %label% %path% with value %value%.',
				Nette\Schema\Message::FailedAssertion,
				['value' => $value, 'assertion' => $expected],
			);
			return null;
		});
	}


	/**
	 * Marks the item as deprecated; emits a warning with the given message when the item is used.
	 */
	public function deprecated(string $message = 'The item %path% is deprecated.'): static
	{
		$this->deprecated = $message;
		return $this;
	}


	/**
	 * Sets a human-readable description of the item; it does not affect validation.
	 */
	public function description(string $description): static
	{
		$this->description = $description;
		return $this;
	}


	/**
	 * The metadata every element reports from describe(); the element adds its own keys.
	 * @return array{required: bool, description: ?string}
	 */
	protected function describeBase(): array
	{
		return ['required' => $this->required, 'description' => $this->description];
	}


	public function completeDefault(Context $context): mixed
	{
		if ($this->required) {
			$context->addError(
				'The mandatory item %path% is missing.',
				Nette\Schema\Message::MissingItem,
			);
			return null;
		}

		return $this->default;
	}


	public function doNormalize(mixed $value, Context $context): mixed
	{
		foreach ($this->before as $handler) {
			$value = $handler($value);
		}

		return $value;
	}


	private function doDeprecation(Context $context): void
	{
		if ($this->deprecated !== null) {
			$context->addWarning(
				$this->deprecated,
				Nette\Schema\Message::Deprecated,
			);
		}
	}


	private function doTransform(mixed $value, Context $context): mixed
	{
		$isOk = $context->createChecker();
		foreach ($this->transforms as $handler) {
			$value = $handler($value, $context);
			if (!$isOk()) {
				return null;
			}
		}
		return $value;
	}


	/** @deprecated use Nette\Schema\Helpers::validateType() */
	private function doValidate(mixed $value, string $expected, Context $context): bool
	{
		$isOk = $context->createChecker();
		Helpers::validateType($value, $expected, $context);
		return $isOk();
	}


	/**
	 * @deprecated use Nette\Schema\Helpers::validateRange()
	 * @param  array{?float, ?float}  $range
	 */
	private static function doValidateRange(mixed $value, array $range, Context $context, string $types = ''): bool
	{
		$isOk = $context->createChecker();
		Helpers::validateRange($value, $range, $context, $types);
		return $isOk();
	}


	/** @deprecated use doTransform() */
	private function doFinalize(mixed $value, Context $context): mixed
	{
		return $this->doTransform($value, $context);
	}
}
