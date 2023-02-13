<?php

namespace ValeSaude\TelemedicineClient\Contracts;

use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;

interface ScheduledTelemedicineProviderInterface
{
    public function getDoctors(?string $specialty = null): DoctorCollection;

    public function getSlotsForDoctor(string $doctorId, ?string $specialty = null): AppointmentSlotCollection;

    public function schedule();

    public function start();
}