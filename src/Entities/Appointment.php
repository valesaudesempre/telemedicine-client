<?php

namespace ValeSaude\TelemedicineClient\Entities;

use Carbon\CarbonImmutable;

class Appointment
{
    private string $id;
    private CarbonImmutable $dateTime;
    private string $status;

    public function __construct(
        string $id,
        CarbonImmutable $dateTime,
        string $status
    ) {
        $this->id = $id;
        $this->dateTime = $dateTime;
        $this->status = $status;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDateTime(): CarbonImmutable
    {
        return $this->dateTime;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}