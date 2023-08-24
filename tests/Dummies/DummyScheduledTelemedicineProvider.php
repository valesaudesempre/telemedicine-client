<?php

namespace ValeSaude\TelemedicineClient\Tests\Dummies;

use BadMethodCallException;
use Carbon\CarbonInterface;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Data\PatientData;
use ValeSaude\TelemedicineClient\Entities\Appointment;
use ValeSaude\TelemedicineClient\Entities\Patient;

class DummyScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface
{
    public function getDoctors(?string $specialty = null): DoctorCollection
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null
    ): AppointmentSlotCollection {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null
    ): DoctorCollection {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getPatient(string $id): ?Patient
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function updateOrCreatePatient(PatientData $data): Patient
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function schedule(string $patientId, string $doctorId, string $slotId): Appointment
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function start()
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function cacheUntil(CarbonInterface $cacheTtl): self
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function withoutCache(): self
    {
        throw new BadMethodCallException('Not implemented.');
    }
}