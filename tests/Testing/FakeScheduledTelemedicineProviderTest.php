<?php

use ValeSaude\TelemedicineClient\Testing\FakeScheduledTelemedicineProvider;

beforeEach(function () {
    $this->sut = new FakeScheduledTelemedicineProvider();
});

test('getDoctors returns empty collection when none was mocked', function () {
    // When
    $doctors = $this->sut->getDoctors();

    // Then
    expect($doctors)->toBeEmpty();
});

test('getDoctors returns mocked doctor', function () {
    // Given
    $doctor = $this->sut->mockExistingDoctor('specialty1');

    // When
    $doctors = $this->sut->getDoctors();

    // Then
    expect($doctors->getItems()[0])->toBe($doctor);
});

test('getDoctors returns mocked doctor filtered by specialty', function () {
    // Given
    $doctor1 = $this->sut->mockExistingDoctor('specialty1');
    $this->sut->mockExistingDoctor('specialty2');

    // When
    $doctors = $this->sut->getDoctors('specialty1');

    // Then
    expect($doctors)->toHaveCount(1)
        ->and($doctors->getItems()[0])->toBe($doctor1);
});

test('getSlotsForDoctor returns empty collection when none was mocked', function () {
    // When
    $slots = $this->sut->getSlotsForDoctor('doctor1');

    // Then
    expect($slots)->toBeEmpty();
});

test('getSlotsForDoctor returns mocked slot', function () {
    // Given
    $slot = $this->sut->mockExistingDoctorSlot('doctor1', 'specialty1');

    // When
    $slots = $this->sut->getSlotsForDoctor('doctor1');

    // Then
    expect($slots->getItems()[0])->toBe($slot);
});

test('getSlotsForDoctor returns mocked slot filtered by specialty', function () {
    // Given
    $slot1 = $this->sut->mockExistingDoctorSlot('doctor1', 'specialty1');
    $slot2 = $this->sut->mockExistingDoctorSlot('doctor1', 'specialty2');

    // When
    $slots = $this->sut->getSlotsForDoctor('doctor1', 'specialty1');

    // Then
    expect($slots)->toHaveCount(1)
        ->and($slots->getItems()[0])->toBe($slot1);
});