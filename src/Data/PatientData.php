<?php

namespace ValeSaude\TelemedicineClient\Data;

use Carbon\CarbonImmutable;
use ValeSaude\LaravelValueObjects\Document;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\LaravelValueObjects\Gender;
use ValeSaude\LaravelValueObjects\Phone;

class PatientData
{
    private FullName $name;
    private Document $document;
    private CarbonImmutable $birthDate;
    private Gender $gender;
    private Email $email;
    private Phone $phone;

    public function __construct(
        FullName $name,
        Document $document,
        CarbonImmutable $birthDate,
        Gender $gender,
        Email $email,
        Phone $phone
    ) {
        $this->name = $name;
        $this->document = $document;
        $this->birthDate = $birthDate;
        $this->gender = $gender;
        $this->email = $email;
        $this->phone = $phone;
    }

    public function getName(): FullName
    {
        return $this->name;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getBirthDate(): CarbonImmutable
    {
        return $this->birthDate;
    }

    public function getGender(): Gender
    {
        return $this->gender;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPhone(): Phone
    {
        return $this->phone;
    }
}