<?php

namespace ValeSaude\TelemedicineClient\Entities;

use Carbon\CarbonImmutable;

class AppointmentSlot
{
    private string $id;
    private CarbonImmutable $dateTime;

    public function __construct(string $id, CarbonImmutable $dateTime)
    {
        $this->id = $id;
        $this->dateTime = $dateTime;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDateTime(): CarbonImmutable
    {
        return $this->dateTime;
    }
}