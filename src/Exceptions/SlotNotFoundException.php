<?php

namespace ValeSaude\TelemedicineClient\Exceptions;

use RuntimeException;

/**
 * @codeCoverageIgnore
 */
class SlotNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Appointment slot not found.');
    }
}