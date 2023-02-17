<?php

namespace ValeSaude\TelemedicineClient\Entities;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use ValeSaude\LaravelValueObjects\Document;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\LaravelValueObjects\Enums\DocumentType;

class Patient
{
    private Document $cpf;
    private string $name;
    private Email $email;
    private string $gender;
    private CarbonImmutable $birthDate;

    public function __construct(
        string $cpf,
        string $name,
        Email $email,
        string $gender,
        CarbonInterface $birthDate
    ) {
        $this->cpf = new Document($cpf, DocumentType::CPF());
        $this->name = $name;
        $this->email = $email;
        $this->gender = $gender;
        $this->birthDate = $birthDate->toImmutable();
    }

    public function getCpf(): Document
    {
        return $this->cpf;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function getBirthDate(): CarbonImmutable
    {
        return $this->birthDate;
    }
}