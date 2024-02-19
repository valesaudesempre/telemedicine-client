<?php

namespace ValeSaude\TelemedicineClient\Contracts;

use ValeSaude\TelemedicineClient\Data\PatientData;
use ValeSaude\TelemedicineClient\Entities\Appointment;

interface SchedulesUsingPatientData
{
    public function scheduleUsingPatientData(string $specialty, string $slotId, PatientData $patientData): Appointment;
}