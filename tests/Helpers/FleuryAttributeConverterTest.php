<?php

namespace ValeSaude\TelemedicineClient\Tests\Helpers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

test('convertCarbonToProviderDate returns a string containing the date formatted as ISO 8601 with UTC timezone', function (CarbonInterface $date, string $expected) {
    // When
    $result = FleuryAttributeConverter::convertCarbonToProviderDate($date);

    // Then
    expect($result)->toBe($expected);
})->with([
    'with UTC timezone' => [
        fn () => CarbonImmutable::parse('2023-01-01', 'UTC'),
        '2023-01-01T00:00:00.000000Z',
    ],
    'with custom timezone' => [
        fn () => CarbonImmutable::parse('2023-01-01', 'America/Sao_Paulo'),
        '2023-01-01T03:00:00.000000Z',
    ],
]);

test('convertProviderAppointmentStatusToLocal returns the expected local status', function (string $providerStatus, string $expectedLocalStatus) {
    // When
    $result = FleuryAttributeConverter::convertProviderAppointmentStatusToLocal($providerStatus);

    // Then
    expect($result)->toBe($expectedLocalStatus);
})->with([
    'SCHEDULED' => ['SCHEDULED', AppointmentStatus::SCHEDULED],
    'CANCELED' => ['CANCELED', AppointmentStatus::CANCELED],
    'COMPLETED' => ['COMPLETED', AppointmentStatus::COMPLETED],
]);

test('convertProviderAppointmentStatusToLocal throws UnexpectedValueException when the provider status is invalid', function () {
    // When
    FleuryAttributeConverter::convertProviderAppointmentStatusToLocal('INVALID');
})->throws(UnexpectedValueException::class, "Invalid status value: 'INVALID'.");