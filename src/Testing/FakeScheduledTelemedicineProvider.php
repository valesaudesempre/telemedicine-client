<?php

namespace ValeSaude\TelemedicineClient\Testing;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ValeSaude\LaravelValueObjects\Money;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Exceptions\AppointmentSlotNotFoundException;
use ValeSaude\TelemedicineClient\Exceptions\DoctorNotFoundException;
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

        $uniqueDoctors = [];

        /** @var Doctor $doctor */
        foreach ($doctors as $doctor) {
            if (array_key_exists($doctor->getId(), $uniqueDoctors)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }

            $uniqueDoctors[$doctor->getId()] = $doctor;
        }

        return new DoctorCollection($uniqueDoctors);
    }

    public function getDoctor(string $doctorId): Doctor
    {
        $filteredDoctors = $this
            ->getDoctors()
            ->filter(fn (Doctor $doctor) => $doctor->getId() === $doctorId)
            ->getItems();
        $doctor = reset($filteredDoctors);

        if (!$doctor) {
            throw DoctorNotFoundException::withId($doctorId);
        }

        return $doctor;
    }

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null
    ): AppointmentSlotCollection {
        $slots = [];

        foreach ($this->slots[$doctorId] ?? [] as $doctorSpecialty => $doctorSlots) {
            if ($specialty && $specialty != $doctorSpecialty) {
                continue;
            }

            $slots = array_merge(
                $slots,
                array_filter(
                    $doctorSlots,
                    static fn (AppointmentSlot $slot) => $until === null || !$slot->getDateTime()->isAfter($until)
                )
            );
        }

        return new AppointmentSlotCollection($slots);
    }

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null
    ): DoctorCollection {
        $doctors = $this->getDoctors($specialty);

        if ($doctorId) {
            $doctors = $doctors->filter(static fn (Doctor $doctor) => $doctor->getId() == $doctorId);
        }

        if ($until) {
            $doctorsWithFilteredSlots = [];

            /** @var Doctor $doctor */
            foreach ($doctors as $doctor) {
                $slots = $this->getSlotsForDoctor($doctor->getId(), $specialty, $until);

                if (!count($slots)) {
                    continue;
                }

                $doctorsWithFilteredSlots[] = new Doctor(
                    $doctor->getId(),
                    $doctor->getName(),
                    $doctor->getGender(),
                    $doctor->getRating(),
                    $doctor->getRegistrationNumber(),
                    $doctor->getPhoto(),
                    $slots
                );
            }

            $doctors = new DoctorCollection($doctorsWithFilteredSlots);
        }

        return $doctors;
    }

    public function getDoctorSlot(string $doctorId, string $slotId): AppointmentSlot
    {
        $filteredSlots = $this
            ->getSlotsForDoctor($doctorId)
            ->filter(fn (AppointmentSlot $slot) => $slot->getId() === $slotId)
            ->getItems();
        $slot = reset($filteredSlots);

        if (!$slot) {
            throw AppointmentSlotNotFoundException::withDoctorIdAndSlotId($doctorId, $slotId);
        }

        return $slot;
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

    /**
     * @codeCoverageIgnore
     */
    public function cacheUntil(CarbonInterface $cacheTtl): self
    {
        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withoutCache(): self
    {
        return $this;
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

        $this->doctors[$specialty][$doctor->getId()] = $doctor;

        return $doctor;
    }

    public function mockExistingDoctorSlot(string $doctorId, string $specialty, ?AppointmentSlot $slot = null): AppointmentSlot
    {
        /** @var Doctor|null $doctor */
        $doctor = $this->doctors[$specialty][$doctorId] ?? null;

        if (!isset($doctor)) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The doctor id is not valid.');
            // @codeCoverageIgnoreEnd
        }

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
        $this->doctors[$specialty][$doctor->getId()] = new Doctor(
            $doctor->getId(),
            $doctor->getName(),
            $doctor->getGender(),
            $doctor->getRating(),
            $doctor->getRegistrationNumber(),
            $doctor->getPhoto(),
            ($doctor->getSlots() ?? new AppointmentSlotCollection())->add($slot)
        );

        return $slot;
    }
}