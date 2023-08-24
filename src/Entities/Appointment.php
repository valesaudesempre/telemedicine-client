<?php

namespace ValeSaude\TelemedicineClient\Entities;

use Carbon\CarbonImmutable;

class Appointment
{
    private string $id;
    private CarbonImmutable $dateTime;
    private ?string $observations;

    public function __construct(
        string $id,
        CarbonImmutable $dateTime,
        ?string $observations = null
    ) {
        $this->id = $id;
        $this->dateTime = $dateTime;
        $this->observations = $observations;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDateTime(): CarbonImmutable
    {
        return $this->dateTime;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }
}