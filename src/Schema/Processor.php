<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;

use Nette;


/**
 * Schema validator.
 */
final class Processor
{
	/** @var list<\Closure(Context): void> */
	public array $onNewContext = [];
	private Context $context;
	private bool $skipDefaults = false;


	/**
	 * When enabled, properties with default values are omitted from the output.
	 */
	public function skipDefaults(bool $value = true): void
	{
		$this->skipDefaults = $value;
	}


	/**
	 * Normalizes and validates data. Result is a clean completed data.
	 * @throws ValidationException
	 */
	public function process(Schema $schema, mixed $data): mixed
	{
		$this->createContext();
		$this->rejectPreventMerging($data);
		$data = $schema->normalize($data, $this->context);
		$this->throwsErrors();
		$data = $schema->complete($data, $this->context);
		$this->throwsErrors();
		return $data;
	}


	/**
	 * Normalizes and validates and merges multiple data. Result is a clean completed data.
	 * @param  list<mixed>  $dataset
	 * @throws ValidationException
	 */
	public function processMultiple(Schema $schema, array $dataset): mixed
	{
		$this->createContext();
		$flatten = null;
		$first = true;
		foreach ($dataset as $data) {
			$this->rejectPreventMerging($data);
			$data = $schema->normalize($data, $this->context);
			$this->throwsErrors();
			$flatten = $first ? $data : $schema->merge($data, $flatten, $this->context);
			$this->throwsErrors();
			$first = false;
		}

		$data = $schema->complete($flatten, $this->context);
		$this->throwsErrors();
		return $data;
	}


	/**
	 * Returns all deprecation warnings collected during the last processing run.
	 * @return list<string>
	 */
	public function getWarnings(): array
	{
		$res = [];
		foreach ($this->context->warnings as $message) {
			$res[] = $message->toString();
		}

		return $res;
	}


	/**
	 * Transitional guard: the magic key was removed in 2.0 and must fail loudly, not flow through as data.
	 */
	private function rejectPreventMerging(mixed $data): void
	{
		if ($data instanceof \stdClass) {
			$data = (array) $data;
		}

		if (is_array($data)) {
			if (array_key_exists('_prevent_merging', $data)) {
				$this->context->addError(
					'The key %path% is no longer supported, use mergeMode() instead.',
					Message::CannotMerge,
				)->path[] = '_prevent_merging';
			}

			foreach ($data as $key => $val) {
				$this->context->path[] = $key;
				$this->rejectPreventMerging($val);
				array_pop($this->context->path);
			}
		}
	}


	private function throwsErrors(): void
	{
		if ($this->context->errors) {
			throw new ValidationException(null, $this->context->errors);
		}
	}


	private function createContext(): void
	{
		$this->context = new Context;
		$this->context->skipDefaults = $this->skipDefaults;
		Nette\Utils\Arrays::invoke($this->onNewContext, $this->context);
	}
}
