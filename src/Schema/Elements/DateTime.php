<?php
/**
 * @noinspection UnusedFunctionResultInspection
 * @noinspection ContractViolationInspection
 */
declare(strict_types=1);

namespace Nette\Schema\Elements;

use DateTimeImmutable;
use DateTimeZone;
use Nette\Schema\Context;
use Nette\Schema\Message;
use Nette\Schema\Schema;

class DateTime implements Schema
{
    /** @var bool */
    private $required = false;

    /** @var bool */
    private $nullable = true;

    /** @var string */
    private $format;

    /** @var DateTimeZone */
    private $timeZone;

    /** @var string */
    protected $output_format;

    public function __construct($format = 'Y-m-d H:i:s', ?DateTimeZone $timeZone = null)
    {
        $this->format = $format;
        $this->timeZone = $timeZone ?? new DateTimeZone(date_default_timezone_get());
    }

    public function required(bool $state = true): self
    {
        $this->required = $state;
        return $this;
    }

    public function nullable(bool $state = true): self
    {
        $this->nullable = $state;
        return $this;
    }

    public function format(string $format): self
    {
        $this->output_format = $format;
        return $this;
    }

    public function normalize($value, Context $context)
    {

        if($this->nullable === true && empty($value)){
            return null;
        }

        // Must be string or empty (null / 0 / false /
        if (is_string($value) === false && empty($value) === false) {
            $type = gettype($value);
            $context->addError("The option %path% expects Date, $type given.", Message::PATTERN_MISMATCH);
            return null;
        }

        if ($this->nullable === false && empty($value)) {
            $context->addError("The option %path% expects not-nullable Date, nothing given.", Message::PATTERN_MISMATCH);
            return null;
        }

        $normalized = DateTimeImmutable::createFromFormat($this->format, $value, $this->timeZone);
        if ($normalized instanceof DateTimeImmutable === false) {
            $context->addError("The option %path% expects Date to match pattern '$this->format', '$value' given.", Message::PATTERN_MISMATCH);
            return null;
        }

        //We should not format DateTime object when we aer called from Date
        if($this->output_format !== null && get_class($this) === __CLASS__) {
            return $normalized->format($this->output_format);
        }

        return $normalized;
    }

    public function merge($value, $base)
    {
        return $value;
    }

    public function complete($value, Context $context)
    {
        return $value;
    }

    /** @noinspection ReturnTypeCanBeDeclaredInspection */
    public function completeDefault(Context $context)
    {
        if ($this->required) {
            $context->addError('The mandatory option %path% is missing.', Message::MISSING_ITEM);
        }
        return null;
    }
}
