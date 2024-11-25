<?php

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ValeSaude\LaravelValueObjects\Document;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\LaravelValueObjects\Gender;
use ValeSaude\LaravelValueObjects\Phone;
use ValeSaude\TelemedicineClient\Builders\PatientBuilder;
use ValeSaude\TelemedicineClient\Contracts\FleuryConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Contracts\ProviderErrorHandlerInterface;
use ValeSaude\TelemedicineClient\Contracts\SharedConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Enums\AppointmentStatus;
use ValeSaude\TelemedicineClient\Exceptions\DoctorNotFoundException;
use ValeSaude\TelemedicineClient\Exceptions\SlotNotFoundException;
use ValeSaude\TelemedicineClient\Helpers\FleuryAttributeConverter;
use ValeSaude\TelemedicineClient\Providers\FleuryScheduledTelemedicineProvider;
use function PHPUnit\Framework\never;
use function PHPUnit\Framework\once;

beforeEach(function () {
    $this->providerBaseUrl = 'http://fleury.url';
    $this->apiKey = 'api-key';
    $this->clientId = 'client-id';
    $this->patient = PatientBuilder::new()
        ->setName(FullName::fromFullNameString('Patient 1'))
        ->setDocument(Document::CPF('27740156507'))
        ->setGender(new Gender('M'))
        ->setBirthDate(Carbon::make('2000-01-01'))
        ->setPhone(new Phone('26666666666'))
        ->setEmail(new Email('27740156507@27740156507.com'))
        ->build();

    $sharedConfigRepositoryMock = $this->createMock(SharedConfigRepositoryInterface::class);
    $sharedConfigRepositoryMock
        ->expects(once())
        ->method('getCache')
        ->willReturn($this->createMock(Repository::class));
    $fleuryConfigRepositoryMock = $this->createMock(FleuryConfigRepositoryInterface::class);
    $errorHandlerMock = $this->createMock(ProviderErrorHandlerInterface::class);
    $fleuryConfigRepositoryMock
        ->expects(once())
        ->method('getBaseUrl')
        ->willReturn($this->providerBaseUrl);
    $fleuryConfigRepositoryMock
        ->expects(once())
        ->method('getApiKey')
        ->willReturn($this->apiKey);
    $fleuryConfigRepositoryMock
        ->expects(once())
        ->method('getClientId')
        ->willReturn($this->clientId);
    $errorHandlerMock
        ->expects(never())
        ->method('handleErrors');

    $this->sut = new FleuryScheduledTelemedicineProvider(
        $sharedConfigRepositoryMock,
        $fleuryConfigRepositoryMock,
        $errorHandlerMock
    );
});

function setFleuryProviderPatientDataForAuthentication(): void
{
    test()->sut->setPatientDataForAuthentication(test()->patient);
}

function fakeFleuryProviderAuthenticationResponse(): void
{
    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/autenticate' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 86400,
        ]),
    ]);
}

function fakeFleuryProviderProfessionalsResponse(): void
{
    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/profissionais*' => Http::response(getFixtureAsJson('providers/fleury/doctors.json')),
    ]);
}

function fakeFleuryProviderSlotsForProfessionalResponse(bool $filteredByDoctorId = false): void
{
    $fixture = $filteredByDoctorId
        ? 'providers/fleury/slots-for-doctor-filtered-by-doctor-id.json'
        : 'providers/fleury/slots-for-doctor.json';

    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/horarios-por-profissional*' => Http::response(getFixtureAsJson($fixture)),
    ]);
}

function fakeFleuryProviderCreateConsultationResponse(): void
{
    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/consultas*' => Http::response([
            'id' => 'appointment-id',
            'date' => '2024-04-19T12:00:00.000Z',
            'status' => 'SCHEDULED',
            'professional' => [
                'name' => 'Dr. John Doe',
            ],
        ]),
    ]);
}

function fakeFleuryProviderGetConsultationResponse(): void
{
    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/consultas/appointment-id' => Http::response([
            'attendance_link' => test()->providerBaseUrl.'/attendance-link/appointment-id',
        ]),
    ]);
}

function fakeFleuryProviderCancelConsultationResponse(): void
{
    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/consultas/appointment-id/cancel' => Http::response([], 204),
    ]);
}

function assertFleuryProviderRequestedWithAccessToken(): void
{
    Http::assertSent(static function (Request $request) {
        return $request->hasHeader('x-authorization-token', 'access-token');
    });
}

test('authenticate throws RuntimeException when patient data is not set', function () {
    $this->sut->authenticate();
})->throws(RuntimeException::class, 'The patient data is not set.');

test('authenticate calls POST integration/cuidado-digital/v1/autenticate with apiKey, clientId and patient data', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();

    // When
    $token = $this->sut->authenticate();

    // Then
    expect($token)->getAccessToken()->toEqual('access-token');
    Http::assertSent(function (Request $request) {
        $data = $request->data();

        return $data['apiKey'] === $this->apiKey &&
            $data['client'] === $this->clientId &&
            $data['name'] === 'Patient 1' &&
            $data['documentNumber'] === '27740156507' &&
            $data['gender'] === 'M' &&
            $data['birth'] === '2000-01-01' &&
            $data['phone'] === '26666666666' &&
            $data['email'] === '27740156507@27740156507.com';
    });
});

test('getDoctors returns a DoctorCollection', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderProfessionalsResponse();

    // When
    $doctors = $this->sut->getDoctors();

    // Then
    expect($doctors->at(0))->getId()->toEqual(1)
        ->getName()->toEqual('Doctor 1')
        ->getRegistrationNumber()->toEqual('CRM-SP 12345')
        ->getPhoto()->toEqual("$this->providerBaseUrl/photos/1.jpg")
        ->getSlots()->toBeNull()
        ->and($doctors->at(1))->getId()->toEqual(2)
        ->getName()->toEqual('Doctor 2')
        ->getRegistrationNumber()->toEqual('CRM-SP 23456')
        ->getPhoto()->toBeNull()
        ->getSlots()->toBeNull();
    assertFleuryProviderRequestedWithAccessToken();
});

test('getDoctors optionally filters the returned doctors', function (array $parameters, array $expectedPayload) {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderProfessionalsResponse();

    // When
    $this->sut->getDoctors(...$parameters);

    // Then
    Http::assertSent(static function (Request $request) use ($expectedPayload) {
        return $request->hasHeader('x-authorization-token', 'access-token') &&
            $request->data() === $expectedPayload;
    });
})->with([
    'only specialty' => [['1234'], ['speciality' => '1234']],
    'only name' => [[null, 'Doctor 1'], ['name' => 'Doctor 1']],
    'both specialty and name' => [['1234', 'Doctor 1'], ['speciality' => '1234', 'name' => 'Doctor 1']],
]);

test('getSlotsForDoctor returns an AppointmentSlotCollection', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderSlotsForProfessionalResponse(true);

    // When
    $slots = $this->sut->getSlotsForDoctor(2);

    // Then
    expect($slots->at(0))->getId()->toEqual(3)
        ->getDateTime()->equalTo('2024-04-19 11:00:00.000')->toBeTrue()
        ->and($slots->at(1))->getId()->toEqual(4)
        ->getDateTime()->equalTo('2024-04-20 14:00:00.000')->toBeTrue();
    assertFleuryProviderRequestedWithAccessToken();
});

test('getSlotsForDoctor optionally filters the returned slots', function (array $parameters, array $expectedPayload) {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderSlotsForProfessionalResponse();

    // When
    $this->sut->getSlotsForDoctor(...array_merge([2], $parameters));

    // Then
    Http::assertSent(static function (Request $request) use ($expectedPayload) {
        $data = $request->data();

        if (!$request->hasHeader('x-authorization-token', 'access-token')) {
            return false;
        }

        if ($data['professional_id'] !== '2') {
            return false;
        }

        if (isset($expectedPayload['speciality'])) {
            return $data['speciality'] === $expectedPayload['speciality'];
        }

        if (isset($expectedPayload['date_end'])) {
            return $data['date_end'] === $expectedPayload['date_end'];
        }

        if (isset($expectedPayload['limitForProfessional'])) {
            return $data['limitForProfessional'] === $expectedPayload['limitForProfessional'];
        }

        return true;
    });
})->with([
    'only specialty' => [['1234'], ['speciality' => '1234']],
    'only until' => [[null, Carbon::parse('2024-04-19', 'UTC')], ['date_end' => '2024-04-19T23:59:59.999999Z']],
    'only limit' => [[null, null, 1], ['limitForProfessional' => 1]],
    'all params' => [
        [
            '1234',
            Carbon::parse('2024-04-19', 'UTC'),
            1,
        ],
        [
            'speciality' => '1234',
            'date_end' => '2024-04-19T23:59:59.999999Z',
            'limitForProfessional' => 1,
        ],
    ],
]);

test('getSlotsForDoctor returns an empty AppointmentSlotCollection when there no doctor is returned by the provider', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/horarios-por-profissional*' => Http::response([]),
    ]);

    // When
    $slots = $this->sut->getSlotsForDoctor(1);

    // Then
    expect($slots)->isEmpty()->toBeTrue();
    assertFleuryProviderRequestedWithAccessToken();
});

test('getDoctorsWithSlots ignores doctors without slots', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/horarios-por-profissional*' => Http::response(getFixtureAsJson('providers/fleury/slots-for-doctor-with-empty-doctor-slots.json')),
    ]);

    // When
    $doctors = $this->sut->getDoctorsWithSlots();

    // Then
    expect($doctors)->toHaveCount(1)
        ->and($doctors->at(0))->getId()->toEqual(1)
        ->getSlots()->not->toBeNull()
        ->getSlots()->toHaveCount(2);
});

test('getDoctorsWithSlots returns a DoctorCollection with Doctor objects including slots property', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderSlotsForProfessionalResponse();

    // When
    $doctors = $this->sut->getDoctorsWithSlots();

    // Then
    /** @var Doctor $doctorAt0 */
    $doctorAt0 = $doctors->at(0);
    /** @var Doctor $doctorAt1 */
    $doctorAt1 = $doctors->at(1);
    expect($doctorAt0)->getId()->toEqual(1)
        ->and($doctorAt0->getSlots())->not->toBeNull()
        ->toHaveCount(2)
        ->at(0)->getId()->toEqual(1)
        ->at(0)->getDateTime()->equalTo('2024-04-16 12:00:00.000')->toBeTrue()
        ->at(1)->getId()->toEqual(2)
        ->at(1)->getDateTime()->equalTo('2024-04-19 15:00:00.000')->toBeTrue()
        ->and($doctorAt1)->getId()->toEqual(2)
        ->and($doctorAt1->getSlots())->not->toBeNull()
        ->toHaveCount(2)
        ->at(0)->getId()->toEqual(3)
        ->at(0)->getDateTime()->equalTo('2024-04-19 11:00:00.000')->toBeTrue()
        ->at(1)->getId()->toEqual(4)
        ->at(1)->getDateTime()->equalTo('2024-04-20 14:00:00.000')->toBeTrue();
    assertFleuryProviderRequestedWithAccessToken();
});

test('getDoctorsWithSlots optionally filters the returned doctors and slots', function (array $parameters, array $expectedPayload) {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderSlotsForProfessionalResponse();

    // When
    $this->sut->getDoctorsWithSlots(...$parameters);

    // Then
    Http::assertSent(static function (Request $request) use ($expectedPayload) {
        $defaultPayload = [
            'date_init' => FleuryAttributeConverter::convertCarbonToProviderDate(today()->startOfDay()),
            'type' => 'REMOTE',
            'appointment_type' => 'DOCTOR_FAMILY',
            'limitForProfessional' => 50,
        ];

        return $request->hasHeader('x-authorization-token', 'access-token') &&
            $request->data() === array_merge($defaultPayload, $expectedPayload);
    });
})->with([
    'only specialty' => [['1234'], ['speciality' => '1234']],
    'only doctorId' => [[null, '1'], ['professional_id' => '1']],
    'only until' => [[null, null, Carbon::parse('2024-04-19', 'UTC')], ['date_end' => '2024-04-19T23:59:59.999999Z']],
    'only limit' => [[null, null, null, 1], ['limitForProfessional' => 1]],
    'all params' => [
        [
            '1234',
            '1',
            Carbon::parse('2024-04-19', 'UTC'),
            1,
        ],
        [
            'speciality' => '1234',
            'professional_id' => '1',
            'date_end' => '2024-04-19T23:59:59.999999Z',
            'limitForProfessional' => 1,
        ],
    ],
]);

test('getDoctorSlot throws DoctorNotFoundException when doctor is not found', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    Http::fake([
        test()->providerBaseUrl.'/integration/cuidado-digital/v1/horarios-por-profissional*' => Http::response([]),
    ]);

    // When
    $this->sut->getDoctorSlot(1, 1);
})->throws(DoctorNotFoundException::class);

test('getDoctorSlot throws SlotNotFoundException when slot is not found for the given doctor', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderSlotsForProfessionalResponse();

    // When
    $this->sut->getDoctorSlot(1, 999);
})->throws(SlotNotFoundException::class);

test('getDoctorSlot returns the matching AppointmentSlot', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderSlotsForProfessionalResponse();

    // When
    $slot = $this->sut->getDoctorSlot(1, 1);

    // Then
    expect($slot)->getId()->toEqual(1)
        ->getDateTime()->equalTo('2024-04-16 12:00:00.000')->toBeTrue();
    assertFleuryProviderRequestedWithAccessToken();
});

test('scheduleUsingPatientData returns an Appointment instance with the appointment data', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderCreateConsultationResponse();

    // When
    $appointment = $this->sut->scheduleUsingPatientData('1', '2', $this->patient);

    // Then
    expect($appointment)->getId()->toEqual('appointment-id')
        ->getDateTime()->toDateTimeString()->toEqual('2024-04-19 12:00:00')
        ->getStatus()->toEqual(AppointmentStatus::SCHEDULED)
        ->getDoctor()->toEqual('Dr. John Doe');
    assertFleuryProviderRequestedWithAccessToken();
});

test('getAppointment returns an Appointment instance with the appointment data', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderCreateConsultationResponse();

    // When
    $appointment = $this->sut->getAppointment('appointment-id');

    // Then
    expect($appointment)->getId()->toEqual('appointment-id')
        ->getDateTime()->toDateTimeString()->toEqual('2024-04-19 12:00:00')
        ->getStatus()->toEqual(AppointmentStatus::SCHEDULED)
        ->getDoctor()->toEqual('Dr. John Doe');
    assertFleuryProviderRequestedWithAccessToken();
});

test('getAppointmentLink returns the appointment link', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderGetConsultationResponse();

    // When
    $link = $this->sut->getAppointmentLink('appointment-id');

    // Then
    expect($link)->toEqual("$this->providerBaseUrl/attendance-link/appointment-id");
    assertFleuryProviderRequestedWithAccessToken();
});

test('cancelAppointment calls PATCH to integration/cuidado-digital/v1/consultas/appointment-id/cancel', function () {
    // Given
    setFleuryProviderPatientDataForAuthentication();
    fakeFleuryProviderAuthenticationResponse();
    fakeFleuryProviderCancelConsultationResponse();

    // When
    $this->sut->cancelAppointment('appointment-id');

    // Then
    assertFleuryProviderRequestedWithAccessToken();
});
