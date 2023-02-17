<?php

namespace ValeSaude\TelemedicineClient\Contracts;

use Carbon\CarbonInterface;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;

interface ScheduledTelemedicineProviderInterface
{
    public function getDoctors(?string $specialty = null): DoctorCollection;

    public function getDoctor(string $doctorId): Doctor;

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

    public function getDoctorSlot(string $doctorId, string $slotId): AppointmentSlot;

    public function schedule();

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