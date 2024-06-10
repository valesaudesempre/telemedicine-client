<?php

use Carbon\CarbonImmutable;
use Faker\Factory;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use ValeSaude\LaravelValueObjects\Document;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\LaravelValueObjects\Gender;
use ValeSaude\LaravelValueObjects\Money;
use ValeSaude\LaravelValueObjects\Phone;
use ValeSaude\TelemedicineClient\Data\PatientData;
use ValeSaude\TelemedicineClient\Entities\Appointment;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Enums\AppointmentStatus;
use ValeSaude\TelemedicineClient\Testing\FakeScheduledTelemedicineProvider;

beforeEach(function () {
    $faker = Factory::create(config('app.faker_locale', Factory::DEFAULT_LOCALE));
    $this->patientData = new PatientData(
        FullName::fromFullNameString($faker->name),
        Document::generateCPF(),
        CarbonImmutable::parse($faker->date()),
        new Gender($faker->randomElement(['M', 'F'])),
        new Email($faker->email),
        new Phone('26666666666')
    );
    $this->sut = new FakeScheduledTelemedicineProvider();
    $this->sut->setPatientDataForAuthentication($this->patientData);
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

test('getDoctors returns mocked doctor filtered by name', function () {
    // Given
    $doctor1 = $this->sut->mockExistingDoctor('specialty1');
    $this->sut->mockExistingDoctor('specialty1');

    // When
    $doctors = $this->sut->getDoctors('specialty1', $doctor1->getName());

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

test('getSlotsForDoctor limits the amount of slots returned', function () {
    // Given
    $doctor = $this->sut->mockExistingDoctor('specialty1');
    $slot = $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');
    $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');

    // When
    $slots = $this->sut->getSlotsForDoctor($doctor->getId(), null, null, 1);

    // Then
    expect($slots)->toHaveCount(1)
        ->and($slots->at(0))->toEqual($slot);
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
            today()->addDay()->toImmutable()
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

test('getDoctorsWithSlots limits the amount of slots returned per doctor', function () {
    // Given
    $doctor1 = $this->sut->mockExistingDoctor('specialty1');
    $doctor2 = $this->sut->mockExistingDoctor('specialty1');
    $slot1 = $this->sut->mockExistingDoctorSlot($doctor1->getId(), 'specialty1');
    $this->sut->mockExistingDoctorSlot($doctor1->getId(), 'specialty1');
    $slot2 = $this->sut->mockExistingDoctorSlot($doctor2->getId(), 'specialty1');
    $this->sut->mockExistingDoctorSlot($doctor2->getId(), 'specialty1');

    // When
    $doctors = $this->sut->getDoctorsWithSlots(null, null, null, 1);

    // Then
    expect($doctors)->toHaveCount(2)
        ->and($doctors->at(0))->getSlots()->toHaveCount(1)
        ->getSlots()->at(0)->toEqual($slot1)
        ->and($doctors->at(1))->getSlots()->toHaveCount(1)
        ->getSlots()->at(0)->toEqual($slot2);
});

test('scheduleUsingPatientData creates a new Appointment', function () {
    // Given
    $doctor = $this->sut->mockExistingDoctor('specialty1');
    $slot = $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');

    // When
    $appointment = $this->sut->scheduleUsingPatientData(
        'specialty1',
        $slot->getId(),
        $this->patientData
    );

    // Then
    $mockedAppointments = $this->sut->getMockedAppointments();
    expect($mockedAppointments)->toHaveCount(1)
        ->and(reset($mockedAppointments))->toEqual([$this->patientData, $appointment, false])
        ->and($appointment->getDateTime())->toEqual($slot->getDateTime())
        ->and($appointment->getStatus())->toEqual(AppointmentStatus::SCHEDULED);
});

test('getAppointment returns the given created appointment', function () {
    // Given
    $existingAppointment = $this->sut->mockExistingAppointment('specialty1', 'slot1', $this->patientData);

    // When
    $appointment = $this->sut->getAppointment($existingAppointment->getId());

    // Then
    expect($appointment)->toEqual($existingAppointment);
});

test('getAppointmentLink returns a string for the given appointment', function () {
    // Given
    $appointment = $this->sut->mockExistingAppointment('specialty1', 'slot1', $this->patientData);

    // When
    $link = $this->sut->getAppointmentLink($appointment->getId());

    // Then
    expect($link)->toEqual("http://some.url/appointments/{$appointment->getId()}");
});

test('cancelAppointment cancels an existing appointment', function () {
    // Given
    $appointment = $this->sut->mockExistingAppointment('specialty1', 'slot1', $this->patientData);

    // When
    $this->sut->cancelAppointment($appointment->getId());

    // Then
    $mockedAppointments = $this->sut->getMockedAppointments();
    $canceledMockedAppointments = $this->sut->getCanceledMockedAppointments();
    expect($mockedAppointments)->toBeEmpty()
        ->and($canceledMockedAppointments)->toHaveCount(1)
        ->and(reset($canceledMockedAppointments))->toEqual([$this->patientData, $appointment, true]);
});

test('assertAppointmentCreated correctly asserts created appointments', function () {
    // Given
    $appointment = $this->sut->mockExistingAppointment('specialtyId', 'slotId', $this->patientData);
    $appointment2 = $this->sut->mockExistingAppointment('specialtyId2', 'slotId2', $this->patientData);
    $this->sut->mockCanceledAppointment($appointment2->getId());
    $appointment3 = $this->sut->mockExistingAppointment('specialtyId3', 'slotId3', $this->patientData);

    // When
    $this->sut->assertAppointmentCreated();
    $this->sut->assertAppointmentCreated(null, true);
    $this->sut->assertAppointmentCreated(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) use ($appointment) {
            return 'specialtyId' === $specialty &&
                'slotId' === $slotId &&
                $patientData == $this->patientData &&
                $appointment->getId() === $existingAppointment->getId();
        }
    );
    $this->sut->assertAppointmentCreated(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) use ($appointment2) {
            return 'specialtyId2' === $specialty &&
                'slotId2' === $slotId &&
                $patientData == $this->patientData &&
                $appointment2->getId() === $existingAppointment->getId();
        },
        true
    );
    // Força a iteração sobre o terceiro appointment, passando pelo cancelado e ignorando-o, visto que não estamos
    // filtrando com $includeCanceled = true
    $this->sut->assertAppointmentCreated(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) use ($appointment3) {
            return 'specialtyId3' === $specialty &&
                'slotId3' === $slotId &&
                $patientData == $this->patientData &&
                $appointment3->getId() === $existingAppointment->getId();
        }
    );
});

test('assertAppointmentCreated throws AssertionFailedError when no assertion is provided and no appointment was created', function () {
    $this->sut->assertAppointmentCreated();
})->throws(AssertionFailedError::class, 'No appointments were created.');

test('assertAppointmentCreated throws AssertionFailedError when assertion is provided and the given appointment was not created', function () {
    // Given
    $this->sut->mockExistingAppointment('specialtyId', 'slotId', $this->patientData);

    // When
    $this->sut->assertAppointmentCreated(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) {
            return 'missing-appointment-id' === $existingAppointment->getId();
        }
    );
})->throws(AssertionFailedError::class, 'The appointment was not created.');

test('assertAppointmentNotCreated correctly asserts missing appointments', function () {
    // Given
    $this->sut->assertAppointmentNotCreated();
    $this->sut->assertAppointmentNotCreated(null, true);
    $this->sut->assertAppointmentNotCreated(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) {
            return 'missing-appointment-id' === $existingAppointment->getId();
        }
    );
    $this->sut->assertAppointmentNotCreated();
    $this->sut->assertAppointmentNotCreated(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) {
            return 'missing-canceled-appointment-id' === $existingAppointment->getId();
        },
        true
    );
});

test('assertAppointmentNotCreated throws AssertionFailedError when no assertion is provided and no appointment was created', function () {
    // Given
    $this->sut->mockExistingAppointment('specialtyId', 'slotId', $this->patientData);

    // When
    $this->sut->assertAppointmentNotCreated();
})->throws(AssertionFailedError::class, 'Some appointments were created.');

test('assertAppointmentNotCreated throws AssertionFailedError when assertion is provided and the given appointment was not created', function () {
    // Given
    $appointment = $this->sut->mockExistingAppointment('specialtyId', 'slotId', $this->patientData);

    // When
    $this->sut->assertAppointmentNotCreated(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) use ($appointment) {
            return 'specialtyId' === $specialty &&
                'slotId' === $slotId &&
                $patientData == $this->patientData &&
                $appointment->getId() === $existingAppointment->getId();
        }
    );
})->throws(AssertionFailedError::class, 'The appointment was created.');

test('assertAppointmentCanceled correctly asserts canceled appointments', function () {
    // Given
    $appointment = $this->sut->mockExistingAppointment('specialtyId', 'slotId', $this->patientData);
    $this->sut->mockCanceledAppointment($appointment->getId());

    // When
    $this->sut->assertAppointmentCanceled();
    $this->sut->assertAppointmentCanceled(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) use ($appointment) {
            return 'specialtyId' === $specialty &&
                'slotId' === $slotId &&
                $patientData == $this->patientData &&
                $appointment->getId() === $existingAppointment->getId();
        }
    );
});

test('assertAppointmentCanceled throws AssertionFailedError when no assertion is provided and no appointments were canceled', function () {
    $this->sut->assertAppointmentCanceled();
})->throws(AssertionFailedError::class, 'No appointments were canceled.');

test('assertAppointmentCanceled throws AssertionFailedError when assertion is provided and the given appointment was not canceled', function () {
    // Given
    $appointment = $this->sut->mockExistingAppointment('specialtyId', 'slotId', $this->patientData);

    // When
    $this->sut->assertAppointmentCanceled(
        function (
            string $specialty,
            string $slotId,
            PatientData $patientData,
            Appointment $existingAppointment
        ) use ($appointment) {
            return $appointment->getId() === $existingAppointment->getId();
        }
    );
})->throws(AssertionFailedError::class, 'The appointment was not canceled.');
