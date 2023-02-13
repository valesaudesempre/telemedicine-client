<?php

namespace ValeSaude\TelemedicineClient\Collections;

use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;

/**
 * @extends AbstractCollection<AppointmentSlot>
 */
class AppointmentSlotCollection extends AbstractCollection
{
    public function getSubjectClass(): string
    {
        return AppointmentSlot::class;
    }
}