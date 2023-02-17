<?php

namespace ValeSaude\TelemedicineClient\Exceptions;

use RuntimeException;

class DoctorNotFoundException extends RuntimeException
{
    public static function withId(string $doctorId): self
    {
        return new self("Doctor not found with ID {$doctorId}.");
    }
}