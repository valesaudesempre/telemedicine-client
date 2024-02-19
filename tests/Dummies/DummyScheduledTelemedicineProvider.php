<?php

namespace ValeSaude\TelemedicineClient\Tests\Dummies;

use BadMethodCallException;
use Carbon\CarbonInterface;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\AuthenticatesUsingPatientDataInterface;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Contracts\SchedulesUsingPatientData;
use ValeSaude\TelemedicineClient\Data\PatientData;
use ValeSaude\TelemedicineClient\Entities\Appointment;

class DummyScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface, SchedulesUsingPatientData, AuthenticatesUsingPatientDataInterface
{
    public function setPatientDataForAuthentication(PatientData $data): AuthenticatesUsingPatientDataInterface
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getDoctors(?string $specialty = null, ?string $name = null): DoctorCollection
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null,
        ?int $limit = null
    ): AppointmentSlotCollection {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null,
        ?int $slotLimit = null
    ): DoctorCollection {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getAppointmentLink(string $appointmentId): string
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function scheduleUsingPatientData(string $specialty, string $slotId, PatientData $patientData): Appointment
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