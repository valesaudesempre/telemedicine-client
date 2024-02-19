<?php

namespace ValeSaude\TelemedicineClient\Entities;

use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;

class Doctor
{
    private string $id;
    private FullName $name;
    private string $registrationNumber;
    private ?string $photo;
    private ?AppointmentSlotCollection $slots;

    public function __construct(
        string $id,
        FullName $name,
        string $registrationNumber,
        ?string $photo = null,
        ?AppointmentSlotCollection $slots = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->registrationNumber = $registrationNumber;
        $this->photo = $photo;
        $this->slots = $slots;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): FullName
    {
        return $this->name;
    }

    public function getRegistrationNumber(): string
    {
        return $this->registrationNumber;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function getSlots(): ?AppointmentSlotCollection
    {
        if (!$this->slots) {
            return null;
        }

        return clone $this->slots;
    }
}