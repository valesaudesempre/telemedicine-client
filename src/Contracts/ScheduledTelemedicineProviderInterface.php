<?php

namespace ValeSaude\TelemedicineClient\Contracts;

use Carbon\CarbonInterface;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Entities\Appointment;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;

interface ScheduledTelemedicineProviderInterface
{
    public function getDoctors(?string $specialty = null, ?string $name = null): DoctorCollection;

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null,
        ?int $limit = null
    ): AppointmentSlotCollection;

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null,
        ?int $slotLimit = null
    ): DoctorCollection;

    public function getDoctorSlot(string $doctorId, string $slotId): AppointmentSlot;

    public function getAppointment(string $appointmentId): Appointment;

    public function getAppointmentLink(string $appointmentId): string;

    public function cancelAppointment(string $appointmentId): void;

    /**
     * @return static
     */
    public function cacheUntil(CarbonInterface $cacheTtl): self;

    /**
     * @return static
     */
    public function withoutCache(): self;
}