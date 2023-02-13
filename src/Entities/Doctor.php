<?php

namespace ValeSaude\TelemedicineClient\Entities;

use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class Doctor
{
    private string $id;
    private string $name;
    private string $gender;
    private Rating $rating;
    private string $registrationNumber;
    private ?string $photo;

    public function __construct(
        string $id,
        string $name,
        string $gender,
        Rating $rating,
        string $registrationNumber,
        ?string $photo = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->gender = $gender;
        $this->rating = $rating;
        $this->registrationNumber = $registrationNumber;
        $this->photo = $photo;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGender(): string
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
}