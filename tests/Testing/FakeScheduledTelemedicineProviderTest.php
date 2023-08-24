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
use ValeSaude\TelemedicineClient\Entities\Patient;
use ValeSaude\TelemedicineClient\Testing\FakeScheduledTelemedicineProvider;

beforeEach(function () {
    $this->sut = new FakeScheduledTelemedicineProvider(Factory::create());
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

test('getPatient returns null when the patient is not mocked', function () {
    expect($this->sut->getPatient('some-patient'))->toBeNull();
});

test('getPatient returns the previously mocked patient', function () {
    // Given
    $previouslyMockedPatient = $this->sut->mockExistingPatient();

    // Then
    expect($this->sut->getPatient($previouslyMockedPatient->getId()))->toBe($previouslyMockedPatient);
});

test('updateOrCreatePatient creates a new patient when none exists with the given document', function () {
    // When
    $patient = $this->sut->updateOrCreatePatient(
        new PatientData(
            new FullName('First', 'Last'),
            Document::generateCPF(),
            CarbonImmutable::today()->subYears(20),
            new Gender('M'),
            new Email('example@example.com'),
            new Phone('26666666666')
        )
    );

    // Then
    expect($this->sut->getMockedPatients())->toHaveCount(1)
        ->and($this->sut->getPatient($patient->getId()))->toBe($patient);
});

test('updateOrCreatePatient updates the existing patient with the provided data', function () {
    // Given
    $existingPatient = $this->sut->mockExistingPatient();

    // When
    $patient = $this->sut->updateOrCreatePatient(
        new PatientData(
            $existingPatient->getName(),
            $existingPatient->getDocument(),
            $existingPatient->getBirthDate(),
            $existingPatient->getGender(),
            $existingPatient->getEmail(),
            $existingPatient->getPhone()
        )
    );

    // Then
    expect($this->sut->getMockedPatients())->toHaveCount(1)
        ->and($this->sut->getPatient($patient->getId()))->toBe($patient);
});

test('schedule creates a new Appointment', function () {
    // Given
    $patient = $this->sut->mockExistingPatient();
    $doctor = $this->sut->mockExistingDoctor('specialty1');
    $slot = $this->sut->mockExistingDoctorSlot($doctor->getId(), 'specialty1');

    // When
    $appointment = $this->sut->schedule(
        'specialty1',
        $patient->getId(),
        $slot->getId()
    );

    // Then
    expect($this->sut->getMockedAppointments())->toHaveCount(1)
        ->toContain($appointment);
});

test('assertPatientCreated correctly asserts created patients', function () {
    // Given
    $patient = $this->sut->mockExistingPatient();

    // When
    $this->sut->assertPatientCreated();
    $this->sut->assertPatientCreated(function (Patient $existingPatient) use ($patient) {
        return $patient->getId() === $existingPatient->getId();
    });
});

test('assertPatientCreated throws AssertionFailedError when no assertion is provided and no patient was created', function () {
    $this->sut->assertPatientCreated();
})->throws(AssertionFailedError::class, 'No patients were created.');

test('assertPatientCreated throws AssertionFailedError when assertion is provided and the given patient was not created', function () {
    // Given
    $this->sut->mockExistingPatient();

    // When
    $this->sut->assertPatientCreated(function (Patient $patient) {
        return $patient->getId() === 'missing-patient-id';
    });
})->throws(AssertionFailedError::class, 'The patient was not created.');

test('assertPatientNotCreated correctly asserts missing patients', function () {
    $this->sut->assertPatientNotCreated();
    $this->sut->assertPatientNotCreated(function (Patient $patient) {
        return $patient->getId() === 'missing-patient-id';
    });
});

test('assertPatientNotCreated throws AssertionFailedError when no assertion is provided and no patient was created', function () {
    // Given
    $this->sut->mockExistingPatient();

    // When
    $this->sut->assertPatientNotCreated();
})->throws(AssertionFailedError::class, 'Some patients were created.');

test('assertPatientNotCreated throws AssertionFailedError when assertion is provided and the given patient was not created', function () {
    // Given
    $patient = $this->sut->mockExistingPatient();

    // When
    $this->sut->assertPatientNotCreated(function (Patient $existingPatient) use ($patient) {
        return $existingPatient->getId() === $patient->getId();
    });
})->throws(AssertionFailedError::class, 'The patient was created.');

// teste

test('assertAppointmentCreated correctly asserts created appointments', function () {
    // Given
    $appointment = $this->sut->mockExistingAppointment('patientId', 'doctorId', 'slotId');

    // When
    $this->sut->assertAppointmentCreated();
    $this->sut->assertAppointmentCreated(function (Appointment $existingAppointment) use ($appointment) {
        return $appointment->getId() === $existingAppointment->getId();
    });
});

test('assertAppointmentCreated throws AssertionFailedError when no assertion is provided and no appointment was created', function () {
    $this->sut->assertAppointmentCreated();
})->throws(AssertionFailedError::class, 'No appointments were created.');

test('assertAppointmentCreated throws AssertionFailedError when assertion is provided and the given appointment was not created', function () {
    // Given
    $this->sut->mockExistingAppointment('patientId', 'doctorId', 'slotId');

    // When
    $this->sut->assertAppointmentCreated(function (Appointment $appointment) {
        return $appointment->getId() === 'missing-appointment-id';
    });
})->throws(AssertionFailedError::class, 'The appointment was not created.');

test('assertAppointmentNotCreated correctly asserts missing appointments', function () {
    $this->sut->assertAppointmentNotCreated();
    $this->sut->assertAppointmentNotCreated(function (Appointment $appointment) {
        return $appointment->getId() === 'missing-appointment-id';
    });
});

test('assertAppointmentNotCreated throws AssertionFailedError when no assertion is provided and no appointment was created', function () {
    // Given
    $this->sut->mockExistingAppointment('patientId', 'doctorId', 'slotId');

    // When
    $this->sut->assertAppointmentNotCreated();
})->throws(AssertionFailedError::class, 'Some appointments were created.');

test('assertAppointmentNotCreated throws AssertionFailedError when assertion is provided and the given appointment was not created', function () {
    // Given
    $appointment = $this->sut->mockExistingAppointment('patientId', 'doctorId', 'slotId');

    // When
    $this->sut->assertAppointmentNotCreated(function (Appointment $existingAppointment) use ($appointment) {
        return $existingAppointment->getId() === $appointment->getId();
    });
})->throws(AssertionFailedError::class, 'The appointment was created.');
