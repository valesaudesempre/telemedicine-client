<?php

namespace ValeSaude\TelemedicineClient\Collections;

use ValeSaude\TelemedicineClient\Entities\Doctor;

/**
 * @extends AbstractCollection<Doctor>
 */
class DoctorCollection extends AbstractCollection
{
    public function getSubjectClass(): string
    {
        return Doctor::class;
    }
}