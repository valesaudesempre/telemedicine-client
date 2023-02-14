<?php

use Illuminate\Support\Str;
use ValeSaude\LaravelValueObjects\Money;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
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
    expect($doctors->at(0))->toEqual($doctor);
});

test('getDoctors returns mocked doctor filtered by specialty', function () {
    // Given
    $doctor1 = $this->sut->mockExistingDoctor('specialty1');
    $this->sut->mockExistingDoctor('specialty2');

    // When
    $doctors = $this->sut->getDoctors('specialty1');

    // Then
    expect($doctors)->toHaveCount(1)
        ->at(0)->toEqual($doctor1);
});

test('getSlotsForDoctor returns empty collection when none was mocked', function () {
    // When

    $slots = $this->sut->getSlotsForDoctor('doctor1');

    // Then
    expect($slots)->toBeEmpty();
});

test('getSlotsForDoctor returns mocked slot', function () {
    // Given
    $doctor = $this->sut->mockExistingDoctor('specialty1');
    $slot = $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');

    // When
    $slots = $this->sut->getSlotsForDoctor($doctor->getId());

    // Then
    expect($slots->at(0))->toEqual($slot);
});

test('getSlotsForDoctor returns mocked slot filtered by specialty', function () {
    // Given
    $doctor = $this->sut->mockExistingDoctor('specialty1');
    $this->sut->mockExistingDoctor('specialty2', $doctor);
    $slot1 = $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');
    $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty2');

    // When
    $slots = $this->sut->getSlotsForDoctor($doctor->getId(), 'specialty1');

    // Then
    expect($slots)->toHaveCount(1)
        ->at(0)->toEqual($slot1);
});

test('getSlotsForDoctor returns mocked slot filtered by date', function () {
    // Given
    $doctor = $this->sut->mockExistingDoctor('specialty1');
    $doctor1slot1 = $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');
    $this->sut->mockExistingDoctorSlot(
        $doctor->getId(),
        'specialty1',
        new AppointmentSlot(
            Str::uuid(),
            today()->addDay()->toImmutable(),
            new Money(10000)
        )
    );

    // Then
    $slots = $this->sut->getSlotsForDoctor($doctor->getId(), 'specialty1', today()->endOfDay());

    // Then
    expect($slots)->toHaveCount(1)
        ->at(0)->getId()->toEqual($doctor1slot1->getId());
});

test('getDoctorsWithSlots returns empty collection when none was mocked', function () {
    // When
    $slots = $this->sut->getDoctorsWithSlots();

    // Then
    expect($slots)->toBeEmpty();
});

test('getDoctorsWithSlots returns mocked doctor with its slots', function () {
    // Given
    $doctor = $this->sut->mockExistingDoctor('specialty1');
    $slot1 = $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');
    $slot2 = $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');

    // When
    $doctors = $this->sut->getDoctorsWithSlots();

    // Then
    expect($doctors->at(0))->getId()->toEqual($doctor->getId())
        ->and($doctors->at(0)->getSlots())->toHaveCount(2)
        ->at(0)->getId()->toEqual($slot1->getId())
        ->at(1)->getId()->toEqual($slot2->getId());
});

test('getDoctorsWithSlots returns mocked doctors filtered by specialty', function () {
    // Given
    $doctor1 = $this->sut->mockExistingDoctor('specialty1');
    $doctor2 = $this->sut->mockExistingDoctor('specialty2');
    $doctor1slot1 = $this->sut->mockExistingDoctorSlot($doctor1->getId(), 'specialty1');
    $doctor1slot2 = $this->sut->mockExistingDoctorSlot($doctor1->getId(), 'specialty1');
    $this->sut->mockExistingDoctorSlot($doctor2->getId(), 'specialty2');
    $this->sut->mockExistingDoctorSlot($doctor2->getId(), 'specialty2');

    // When
    $doctors = $this->sut->getDoctorsWithSlots('specialty1');

    // Then
    expect($doctors)->toHaveCount(1)
        ->at(0)->getId()->toEqual($doctor1->getId())
        ->and($doctors->at(0)->getSlots())->toHaveCount(2)
        ->at(0)->getId()->toEqual($doctor1slot1->getId())
        ->at(1)->getId()->toEqual($doctor1slot2->getId());
});

test('getDoctorsWithSlots returns mocked doctors filtered by doctor id', function () {
    // Given
    $doctor1 = $this->sut->mockExistingDoctor('specialty1');
    $doctor2 = $this->sut->mockExistingDoctor('specialty1');
    $slot1 = $this->sut->mockExistingDoctorSlot($doctor1->getId(), 'specialty1');
    $slot2 = $this->sut->mockExistingDoctorSlot($doctor2->getId(), 'specialty1');

    // When
    $doctors = $this->sut->getDoctorsWithSlots(null, $doctor1->getId());

    // Then
    expect($doctors)->toHaveCount(1)
        ->at(0)->getId()->toEqual($doctor1->getId())
        ->and($doctors->at(0)->getSlots())->toHaveCount(1)
        ->at(0)->getId()->toEqual($slot1->getId());
});

test('getDoctorsWithSlots returns mocked slot filtered by date', function () {
    // Given
    $doctor1 = $this->sut->mockExistingDoctor('specialty1');
    $doctor2 = $this->sut->mockExistingDoctor('specialty1');
    $slot1 = $this->sut->mockExistingDoctorSlot($doctor1->getId(), 'specialty1');
    $slot2 = $this->sut->mockExistingDoctorSlot(
        $doctor2->getId(),
        'specialty1',
        new AppointmentSlot(
            Str::uuid(),
            today()->addDay()->toImmutable(),
            new Money(10000)
        )
    );

    // Then
    $doctors = $this->sut->getDoctorsWithSlots(null, null, today()->endOfDay());

    // Then
    expect($doctors)->toHaveCount(1)
        ->at(0)->getId()->toEqual($doctor1->getId())
        ->and($doctors->at(0)->getSlots())->toHaveCount(1)
        ->at(0)->getId()->toEqual($slot1->getId());
});