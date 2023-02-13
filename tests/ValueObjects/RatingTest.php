<?php

use ValeSaude\LaravelValueObjects\Exceptions\InvalidValueObjectValueException;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

it('throws when given value is greater than 10', fn () => new Rating(11))
    ->throws(InvalidValueObjectValueException::class, 'The rating value must be between 0 and 10');

it('throws when given value is lower than 0', fn () => new Rating(-1))
    ->throws(InvalidValueObjectValueException::class, 'The rating value must be between 0 and 10');