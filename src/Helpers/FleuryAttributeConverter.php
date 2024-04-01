<?php

namespace ValeSaude\TelemedicineClient\Helpers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use UnexpectedValueException;
use ValeSaude\TelemedicineClient\Enums\AppointmentStatus;

final class FleuryAttributeConverter
{
    public static function convertProviderDateToCarbon(string $date): CarbonImmutable
    {
        $timezone = config('app.timezone', 'UTC');

        return CarbonImmutable::parse($date, 'UTC')->setTimezone($timezone);
    }

    public static function convertCarbonToProviderDate(CarbonInterface $date): string
    {
        return $date->toISOString();
    }

    public static function convertProviderAppointmentStatusToLocal(string $status): string
    {
        switch ($status) {
            case 'SCHEDULED':
                return AppointmentStatus::SCHEDULED;
            case 'CANCELED':
                return AppointmentStatus::CANCELED;
            default:
                throw new UnexpectedValueException("Invalid status value: '$status'.");
        }
    }
}