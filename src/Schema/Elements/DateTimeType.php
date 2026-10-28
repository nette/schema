<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\{Context, Kind, Message};
use function is_string;


/**
 * A date or a date with time: accepts a DateTimeInterface or a string in ISO 8601 or the given format
 * and yields DateTimeImmutable.
 */
final class DateTimeType extends Type
{
	private const DatePattern = '~^(\d{4})-(\d\d)-(\d\d)$~D';
	private const IsoPattern = '~^(\d{4})-(\d\d)-(\d\d)(?:[T ](?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d+)?)?(?:Z|[+-](?:[01]\d|2[0-3]):?[0-5]\d)?)?$~D';

	/** @var array{?\DateTimeImmutable, ?\DateTimeImmutable} */
	private array $range = [null, null];


	public function __construct(
		private bool $date = false,
		private ?string $format = null,
	) {
		parent::__construct(\DateTimeImmutable::class);
	}


	/**
	 * A string is converted right away, so the default is a DateTimeImmutable like the processed value.
	 */
	public function default(mixed $value): static
	{
		return parent::default($value === null ? null : $this->parseBound($value));
	}


	public function min(\DateTimeInterface|string|int|float|null $min): static
	{
		$this->range[0] = $min === null ? null : $this->parseBound($min);
		return $this;
	}


	public function max(\DateTimeInterface|string|int|float|null $max): static
	{
		$this->range[1] = $max === null ? null : $this->parseBound($max);
		return $this;
	}


	public function describe(): array
	{
		return [
			'kind' => Kind::DateTime,
			'date' => $this->date,
			'format' => $this->format,
			'min' => $this->range[0],
			'max' => $this->range[1],
		] + parent::describe();
	}


	protected function coerce(mixed $value): mixed
	{
		return $this->parse($value) ?? $value;
	}


	protected function validate(mixed $value, Context $context): mixed
	{
		$isOk = $context->createChecker();
		parent::validate($value, $context);
		if (
			$isOk()
			&& $value instanceof \DateTimeImmutable
			&& !$context->isPartial
			&& (($this->range[0] && $value < $this->range[0]) || ($this->range[1] && $value > $this->range[1]))
		) {
			$context->addError(
				'The %label% %path% expects to be in range %expected%, %date% given.',
				Message::ValueOutOfRange,
				[
					'value' => $value,
					'expected' => $this->formatDate($this->range[0]) . '..' . $this->formatDate($this->range[1]),
					'date' => $this->formatDate($value),
				],
			);
		}
		return $value;
	}


	protected function formatExpected(): string
	{
		$name = ($this->date ? 'date' : 'date-time') . ' (' . ($this->format ?? ($this->date ? 'Y-m-d' : 'ISO 8601')) . ')';
		return str_replace(['|', \DateTimeImmutable::class], [' or ', $name], $this->getExpression());
	}


	private function parse(mixed $value): ?\DateTimeImmutable
	{
		if ($value instanceof \DateTimeInterface) {
			$res = \DateTimeImmutable::createFromInterface($value);
		} elseif (!is_string($value)) {
			return null;
		} elseif ($this->format !== null) {
			$res = \DateTimeImmutable::createFromFormat('!' . $this->format, $value);
			if ($res === false || \DateTimeImmutable::getLastErrors() !== false) { // an overflow like February 30 is a warning
				return null;
			}
		} elseif (
			preg_match($this->date ? self::DatePattern : self::IsoPattern, $value, $m)
			&& checkdate((int) $m[2], (int) $m[3], (int) $m[1])
		) {
			$res = new \DateTimeImmutable($value);
		} else {
			return null;
		}

		return $this->date ? $res->setTime(0, 0) : $res;
	}


	private function parseBound(mixed $value): \DateTimeImmutable
	{
		return $this->parse($value) ?? throw new Nette\InvalidArgumentException(
			'Value ' . Nette\Schema\Helpers::formatValue($value) . ' is not a valid ' . $this->formatExpected() . '.',
		);
	}


	private function formatDate(?\DateTimeImmutable $date): string
	{
		return $date?->format($this->format ?? ($this->date ? 'Y-m-d' : 'Y-m-d H:i:s P')) ?? '';
	}
}
