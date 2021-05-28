<?php

declare(strict_types=1);

namespace Nette\Schema\Elements;

use DateTimeImmutable;
use DateTimeZone;
use Nette\Schema\Context;

class Date extends DateTime
{

    public function __construct($format = 'Y-m-d', ?DateTimeZone $timeZone = null)
    {
        parent::__construct($format, $timeZone);
    }

    public function normalize($value, Context $context)
    {
        $normalized = parent::normalize($value, $context);

        if ($normalized instanceof DateTimeImmutable) {
            $normalized = $normalized->setTime(0, 0);
            if($this->output_format !== null) {
                return $normalized->format($this->output_format);
            }
        }

        return $normalized;
    }
}
