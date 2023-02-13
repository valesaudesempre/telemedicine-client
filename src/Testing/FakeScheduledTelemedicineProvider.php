<?php

namespace ValeSaude\TelemedicineClient\Testing;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ValeSaude\LaravelValueObjects\Money;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class FakeScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface
{
    /** @var array<string, Doctor[]> */
    private array $doctors = [];

    /** @var array<string, array<string, AppointmentSlot[]>> */
    private array $slots = [];

    public function getDoctors(?string $specialty = null): DoctorCollection
    {
        $doctors = [];

        if ($specialty) {
            $doctors = $this->doctors[$specialty] ?? [];
        } else {
            $doctors = Arr::flatten($this->doctors);
        }

        return new DoctorCollection($doctors);
    }

    public function getSlotsForDoctor(string $doctorId, ?string $specialty = null): AppointmentSlotCollection
    {
        $slots = [];

        foreach ($this->slots[$doctorId] ?? [] as $doctorSpecialty => $doctorSlots) {
            if ($specialty && $specialty != $doctorSpecialty) {
                continue;
            }

            $slots = array_merge($slots, $doctorSlots);
        }

        return new AppointmentSlotCollection($slots);
    }

    /**
     * @codeCoverageIgnore
     */
    public function schedule()
    {
        throw new BadMethodCallException('Not implemented.');
    }

    /**
     * @codeCoverageIgnore
     */
    public function start()
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function mockExistingDoctor(string $specialty, ?Doctor $doctor = null): Doctor
    {
        if (!$doctor) {
            $doctor = new Doctor(
                (string) Str::uuid(),
                'Fake Doctor',
                'M',
                new Rating(10),
                'CRM-SP 12345'
            );
        }

        if (!array_key_exists($specialty, $this->doctors)) {
            $this->doctors[$specialty] = [];
        }

        $this->doctors[$specialty][] = $doctor;

        return $doctor;
    }

    public function mockExistingDoctorSlot(string $doctorId, string $specialty, ?AppointmentSlot $slot = null): AppointmentSlot
    {
        if (!$slot) {
            $slot = new AppointmentSlot(
                (string) Str::uuid(),
                CarbonImmutable::now()->addHour()->startOfHour(),
                new Money(10000)
            );
        }

        if (!array_key_exists($doctorId, $this->slots)) {
            $this->slots[$doctorId] = [];
        }

        if (!array_key_exists($specialty, $this->slots[$doctorId])) {
            $this->slots[$doctorId][$specialty] = [];
        }

        $this->slots[$doctorId][$specialty][] = $slot;

        return $slot;
    }
}