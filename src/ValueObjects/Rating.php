<?php

namespace ValeSaude\TelemedicineClient\ValueObjects;

use ValeSaude\LaravelValueObjects\AbstractValueObject;
use ValeSaude\LaravelValueObjects\Exceptions\InvalidValueObjectValueException;

class Rating extends AbstractValueObject
{
    public float $value;

    public function __construct(float $value)
    {
        if ($value < 0 || $value > 10) {
            throw new InvalidValueObjectValueException('The rating value must be between 0 and 10');
        }

        $this->value = round($value, 2);
    }

    public function getValue(): float
    {
        return $this->value;
    }
}