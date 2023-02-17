<?php

namespace ValeSaude\TelemedicineClient\Builders;

use Carbon\CarbonInterface;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\TelemedicineClient\Entities\Patient;

class PatientBuilder
{
    private string $cpf;
    private string $name;
    private Email $email;
    private string $gender;
    private CarbonInterface $birthDate;

    public function setCpf(string $cpf): self
    {
        $this->cpf = $cpf;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setEmail(Email $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function setGender(string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function setBirthDate(CarbonInterface $birthDate): self
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function get(): Patient
    {
        return new Patient(
            $this->cpf,
            $this->name,
            $this->email,
            $this->gender,
            $this->birthDate
        );
    }

    public static function new(): self
    {
        return new self();
    }
}