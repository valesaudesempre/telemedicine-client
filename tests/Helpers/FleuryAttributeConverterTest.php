<?php

namespace ValeSaude\TelemedicineClient\Tests\Helpers;

use UnexpectedValueException;
use ValeSaude\TelemedicineClient\Enums\AppointmentStatus;
use ValeSaude\TelemedicineClient\Helpers\FleuryAttributeConverter;

test('convertProviderDateToCarbon returns a CarbonImmutable instance with expected timezone', function () {
    // Given
    config()->set('app.timezone', 'America/Sao_Paulo');

    // When
    $result = FleuryAttributeConverter::convertProviderDateToCarbon('2023-01-01T12:00:00Z');

    // Then
    expect($result)->format('Y-m-d H:i:s')->toBe('2023-01-01 09:00:00')
        ->getTimezone()->getName()->toBe('America/Sao_Paulo');
});

test('convertProviderAppointmentStatusToLocal returns the expected local status', function (string $providerStatus, string $expectedLocalStatus) {
    // When
    $result = FleuryAttributeConverter::convertProviderAppointmentStatusToLocal($providerStatus);

    // Then
    expect($result)->toBe($expectedLocalStatus);
})->with([
    'SCHEDULED' => ['SCHEDULED', AppointmentStatus::SCHEDULED],
    'CANCELED' => ['CANCELED', AppointmentStatus::CANCELED],
]);

test('convertProviderAppointmentStatusToLocal throws UnexpectedValueException when the provider status is invalid', function () {
    // When
    FleuryAttributeConverter::convertProviderAppointmentStatusToLocal('INVALID');
})->throws(UnexpectedValueException::class, "Invalid status value: 'INVALID'.");