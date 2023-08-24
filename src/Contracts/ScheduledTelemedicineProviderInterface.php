<?php

namespace ValeSaude\TelemedicineClient\Contracts;

use Carbon\CarbonInterface;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Data\PatientData;
use ValeSaude\TelemedicineClient\Entities\Appointment;
use ValeSaude\TelemedicineClient\Entities\Patient;

interface ScheduledTelemedicineProviderInterface
{
    public function getDoctors(?string $specialty = null): DoctorCollection;

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null
    ): AppointmentSlotCollection;

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null
    ): DoctorCollection;

    public function getPatient(string $id): ?Patient;

    public function updateOrCreatePatient(PatientData $data): Patient;

    public function schedule(string $specialty, string $patientId, string $slotId): Appointment;

    public function start();

    /**
     * @return static
     */
    public function cacheUntil(CarbonInterface $cacheTtl): self;

    /**
     * @return static
     */
    public function withoutCache(): self;
}