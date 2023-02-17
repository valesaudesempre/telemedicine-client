<?php

namespace ValeSaude\TelemedicineClient\Exceptions;

use RuntimeException;

class AppointmentSlotNotFoundException extends RuntimeException
{
    public static function withDoctorIdAndSlotId(string $doctorId, string $slotId): self
    {
        return new self("Slot with ID {$slotId} not found for doctor {$doctorId}.");
    }
}