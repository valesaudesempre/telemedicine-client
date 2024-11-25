<?php

namespace ValeSaude\TelemedicineClient\Exceptions;

use RuntimeException;

/**
 * @codeCoverageIgnore
 */
class DoctorNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Doctor not found.');
    }
}