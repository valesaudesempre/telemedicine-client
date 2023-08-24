<?php

namespace ValeSaude\TelemedicineClient\Entities;

use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\LaravelValueObjects\Gender;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class Doctor
{
    private string $id;
    private FullName $name;
    private Gender $gender;
    private Rating $rating;
    private string $registrationNumber;
    private ?string $photo;
    private ?AppointmentSlotCollection $slots;

    public function __construct(
        string $id,
        FullName $name,
        Gender $gender,
        Rating $rating,
        string $registrationNumber,
        ?string $photo = null,
        ?AppointmentSlotCollection $slots = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->gender = $gender;
        $this->rating = $rating;
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

    public function getGender(): Gender
    {
        return $this->gender;
    }

    public function getRating(): Rating
    {
        return $this->rating;
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