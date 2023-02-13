<?php

namespace ValeSaude\TelemedicineClient\Entities;

use Carbon\CarbonImmutable;
use ValeSaude\LaravelValueObjects\Money;

class AppointmentSlot
{
    private string $id;
    private CarbonImmutable $dateTime;
    private Money $price;

    public function __construct(string $id, CarbonImmutable $dateTime, Money $price)
    {
        $this->id = $id;
        $this->dateTime = $dateTime;
        $this->price = $price;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDateTime(): CarbonImmutable
    {
        return $this->dateTime;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }
}