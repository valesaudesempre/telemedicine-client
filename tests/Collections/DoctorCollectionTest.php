<?php

use Carbon\CarbonImmutable;
use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use function PHPUnit\Framework\any;
use function PHPUnit\Framework\once;

test('includesSlots returns a boolean indicating if the collection has doctors with slots', function (DoctorCollection $collection, bool $expected) {
    expect($collection->includesSlots())->toEqual($expected);
})->with([
    'doctor with null slots property' => [
        function () {
            $doctor1 = test()->createMock(Doctor::class);
            $doctor1->expects(once())
                ->method('getSlots')
                ->willReturn(test()->createMock(AppointmentSlotCollection::class));
            $doctor2 = test()->createMock(Doctor::class);
            $doctor2->expects(once())
                ->method('getSlots')
                ->willReturn(null);

            return new DoctorCollection([$doctor1, $doctor2]);
        },
        false,
    ],
    'doctors with slots' => [
        function () {
            $doctor1 = test()->createMock(Doctor::class);
            $doctor1->expects(once())
                ->method('getSlots')
                ->willReturn(test()->createMock(AppointmentSlotCollection::class));
            $doctor2 = test()->createMock(Doctor::class);
            $doctor2->expects(once())
                ->method('getSlots')
                ->willReturn(test()->createMock(AppointmentSlotCollection::class));

            return new DoctorCollection([$doctor1, $doctor2]);
        },
        true,
    ],
]);

test('sortByDoctorSlot throws RuntimeException when some doctors does not have slots', function () {
    // Given
    $doctor1 = $this->createMock(Doctor::class);
    $doctor1->expects(once())
        ->method('getSlots')
        ->willReturn(null);
    $collection = new DoctorCollection([$doctor1]);

    // When
    $collection->sortByDoctorSlot();
})->throws(RuntimeException::class, 'Cannot sort doctors without slots.');

test('sortByDoctorSlot returns the doctors sorted by slot datetime, then by doctor name', function () {
    // Given
    $doctor1 = $this->createMock(Doctor::class);
    $doctor1->expects(any())
        ->method('getName')
        ->willReturn(FullName::fromFullNameString('Doctor 3'));
    $doctor1Slot1 = $this->createMock(AppointmentSlot::class);
    $doctor1Slot1->expects(any())
        ->method('getDateTime')
        ->willReturn(CarbonImmutable::parse('2023-01-01 10:00:00'));
    $doctor1Slot2 = $this->createMock(AppointmentSlot::class);
    $doctor1Slot2->expects(any())
        ->method('getDateTime')
        ->willReturn(CarbonImmutable::parse('2023-01-02 09:00:00'));
    $doctor1Slots = new AppointmentSlotCollection([$doctor1Slot2, $doctor1Slot1]);
    $doctor1->expects(any())
        ->method('getSlots')
        ->willReturn($doctor1Slots);
    $doctor2 = $this->createMock(Doctor::class);
    $doctor2->expects(any())
        ->method('getName')
        ->willReturn(FullName::fromFullNameString('Doctor 2'));
    $doctor2Slot1 = $this->createMock(AppointmentSlot::class);
    $doctor2Slot1->expects(any())
        ->method('getDateTime')
        ->willReturn(CarbonImmutable::parse('2023-01-02 10:00:00'));
    $doctor2Slot2 = $this->createMock(AppointmentSlot::class);
    $doctor2Slot2->expects(any())
        ->method('getDateTime')
        ->willReturn(CarbonImmutable::parse('2023-01-03 10:00:00'));
    $doctor2Slots = new AppointmentSlotCollection([$doctor2Slot2, $doctor2Slot1]);
    $doctor2->expects(any())
        ->method('getSlots')
        ->willReturn($doctor2Slots);
    $doctor3 = $this->createMock(Doctor::class);
    $doctor3->expects(any())
        ->method('getName')
        ->willReturn(FullName::fromFullNameString('Doctor 1'));
    $doctor3Slot1 = $this->createMock(AppointmentSlot::class);
    $doctor3Slot1->expects(any())
        ->method('getDateTime')
        ->willReturn(CarbonImmutable::parse('2023-01-02 10:00:00'));
    $doctor3Slot2 = $this->createMock(AppointmentSlot::class);
    $doctor3Slot2->expects(any())
        ->method('getDateTime')
        ->willReturn(CarbonImmutable::parse('2023-01-02 11:00:00'));
    $doctor3Slots = new AppointmentSlotCollection([$doctor3Slot2, $doctor3Slot1]);
    $doctor3->expects(any())
        ->method('getSlots')
        ->willReturn($doctor3Slots);
    $collection = new DoctorCollection([$doctor3, $doctor1, $doctor2]);

    // When
    $sorted = $collection->sortByDoctorSlot();

    // Then
    // Doctor 3 has the earliest slot datetime
    // Both Doctor 1 and Doctor 2 have the same earliest slot datetime, but Doctor 1 comes first alphabetically
    expect($sorted)->toHaveCount(3)
        ->and($sorted->at(0))->getName()->toEqual('Doctor 3')
        ->and($sorted->at(1))->getName()->toEqual('Doctor 1')
        ->and($sorted->at(2))->getName()->toEqual('Doctor 2');
});