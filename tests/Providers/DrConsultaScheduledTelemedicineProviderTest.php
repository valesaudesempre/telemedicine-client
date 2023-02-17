<?php

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Entities\Patient;
use ValeSaude\TelemedicineClient\Exceptions\AppointmentSlotNotFoundException;
use ValeSaude\TelemedicineClient\Exceptions\DoctorNotFoundException;
use ValeSaude\TelemedicineClient\Providers\DrConsultaScheduledTelemedicineProvider;

beforeEach(function () {
    $this->clientId = 'client-id';
    $this->secret = 'secret';
    $this->marketplaceBaseUrl = 'http://marketplace.dr-consulta.url';
    $this->marketplaceDefaultUnitId = 1234;
    $this->subscriptionBaseUrl = 'http://subscription.dr-consulta.url';
    $this->subscriptionUsername = 'username';
    $this->subscriptionPassword = 'password';
    $this->subscriptionPartnerCode = 'partner-code';

    $this->cacheMock = $this->createMock(CacheRepository::class);
    $this->sut = new DrConsultaScheduledTelemedicineProvider(
        $this->clientId,
        $this->secret,
        $this->marketplaceBaseUrl,
        $this->marketplaceDefaultUnitId,
        $this->subscriptionBaseUrl,
        $this->subscriptionUsername,
        $this->subscriptionPassword,
        $this->subscriptionPartnerCode,
        $this->cacheMock
    );
});

function fakeDrConsultaProviderMarketplaceAuthenticationResponse(): void
{
    Http::fake([test()->marketplaceBaseUrl.'/v1/login/auth' => Http::response(['access_token' => 'some-token'])]);
}

function fakeDrConsultaProviderMarketplaceAvailableSlotsResponse(): void
{
    Http::fake([
        test()->marketplaceBaseUrl.'/v1/profissional/slotsAtivos*' => Http::response(getFixtureAsJson('providers/dr-consulta/doctors.json')),
    ]);
}

function fakeDrConsultaProviderMarketplaceScheduleResponse(): string
{
    $hash = (string) Str::uuid();

    Http::fake([test()->marketplaceBaseUrl.'/v1/agendamento' => Http::response(compact('hash'))]);

    return $hash;
}

function fakeDrConsultaProviderSubscriptionPatientResponse(): string
{
    $patientId = (string) Str::uuid();

    Http::fake([test()->subscriptionBaseUrl.'/v1/subscription' => Http::response(['id_paciente' => $patientId])]);

    return $patientId;
}

function assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken(): void
{
    Http::assertSent(static function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer some-token') &&
            $request->data() === ['idUnidade' => test()->marketplaceDefaultUnitId];
    });
}

test('authenticate calls POST v1/login/auth with clientId and secret', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();

    // When
    $token = $this->sut->authenticateMarketplace();

    // Then
    expect($token)->toEqual('some-token');
    Http::assertSent(function (Request $request) {
        return $request->data() === ['client_id' => $this->clientId, 'secret' => $this->secret];
    });
});

test('getDoctors returns a DoctorCollection', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $doctors = $this->sut->getDoctors();

    // Then
    expect($doctors->at(0))->getId()->toEqual(1)
        ->getName()->toEqual('Doctor 1')
        ->getGender()->toEqual('F')
        ->getRating()->getValue()->toEqual(9.4)
        ->getRegistrationNumber()->toEqual('CRM-SP 12345')
        ->getPhoto()->toEqual("$this->marketplaceBaseUrl/photos/1.jpg")
        ->getSlots()->toBeNull()
        ->and($doctors->at(1))->getId()->toEqual(2)
        ->getName()->toEqual('Doctor 2')
        ->getGender()->toEqual('M')
        ->getRating()->getValue()->toEqual(9.8)
        ->getRegistrationNumber()->toEqual('CRM-SP 23456')
        ->getPhoto()->toBeNull()
        ->getSlots()->toBeNull();
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getDoctors optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $this->sut->getDoctors(1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer some-token') &&
            $request->data() === ['idUnidade' => $this->marketplaceDefaultUnitId, 'idProduto' => '1234'];
    });
});

test('getDoctor throws DoctorNotFoundException when doctor is not found with given ID', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // Then
    $this->expectExceptionObject(DoctorNotFoundException::withId('some-doctor-id'));

    // When
    $this->sut->getDoctor('some-doctor-id');
});

test('getDoctor returns doctor with given ID', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $doctor = $this->sut->getDoctor(1);

    // Then
    expect($doctor)->getName()->toEqual('Doctor 1')
        ->getGender()->toEqual('F')
        ->getRating()->getValue()->toEqual(9.4)
        ->getRegistrationNumber()->toEqual('CRM-SP 12345')
        ->getPhoto()->toEqual("$this->marketplaceBaseUrl/photos/1.jpg")
        ->getSlots()->toBeNull();
});

test('getDoctor returns doctor with given ID and its slots, when withSlots parameter is true', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $doctor = $this->sut->getDoctor(1, true);

    // Then
    expect($doctor->getSlots()->at(0))->getId()->toEqual(1)
        ->getDateTime()->equalTo('2023-02-17 15:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(5000)
        ->and($doctor->getSlots()->at(1))->getId()->toEqual(2)
        ->getDateTime()->equalTo('2023-02-18 15:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(5000);
});

test('getSlotsForDoctor returns a AppointmentSlotCollection', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $slots = $this->sut->getSlotsForDoctor(2);

    // Then
    expect($slots->at(0))->getId()->toEqual(3)
        ->getDateTime()->equalTo('2023-02-18 16:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(6500)
        ->and($slots->at(1))->getId()->toEqual(4)
        ->getDateTime()->equalTo('2023-02-19 16:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(6500);
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getSlotsForDoctor optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $this->sut->getSlotsForDoctor(1, 1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer some-token') &&
            $request->data() === ['idUnidade' => $this->marketplaceDefaultUnitId, 'idProduto' => '1234'];
    });
});

test('getSlotsForDoctor optionally filters slots up to a given date/time', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $slots = $this->sut->getSlotsForDoctor(1, null, Carbon::make('2023-02-17 23:59:59'));

    // Then
    expect($slots)->toHaveCount(1)
        ->at(0)->getId()->toEqual(1);
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getDoctorsWithSlots returns a DoctorCollection with Doctor objects including slots property', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

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
        ->at(1)->getId()->toEqual(2)
        ->and($doctorAt1)->getId()->toEqual(2)
        ->and($doctorAt1->getSlots())->not->toBeNull()
        ->toHaveCount(2)
        ->at(0)->getId()->toEqual(3)
        ->at(1)->getId()->toEqual(4);
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getDoctorsWithSlots optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $this->sut->getDoctorsWithSlots(1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer some-token') &&
            $request->data() === ['idUnidade' => $this->marketplaceDefaultUnitId, 'idProduto' => '1234'];
    });
});

test('getDoctorsWithSlots optionally filters by doctor id', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $doctors = $this->sut->getDoctorsWithSlots(null, 1);

    // Then
    expect($doctors)->toHaveCount(1)
        ->and($doctors->at(0)->getSlots())->toHaveCount(2);
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getDoctorsWithSlots optionally filters doctor slots up to a given date/time', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $doctors = $this->sut->getDoctorsWithSlots(null, null, Carbon::make('2023-02-18 23:59:59'));

    // Then
    expect($doctors)->toHaveCount(2)
        ->and($doctors->at(0)->getSlots())->toHaveCount(2)
        ->at(0)->getId()->toEqual(1)
        ->at(1)->getId()->toEqual(2)
        ->and($doctors->at(1)->getSlots())->toHaveCount(1)
        ->at(0)->getId(3);
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getDoctorsWithSlots ignores doctors without slots up to given date', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $doctors = $this->sut->getDoctorsWithSlots(null, null, Carbon::make('2023-02-18 15:00:00.000'));

    // Then
    expect($doctors)->toHaveCount(1)
        ->and($doctors->at(0)->getSlots())->toHaveCount(2)
        ->at(0)->getId()->toEqual(1)
        ->at(1)->getId()->toEqual(2);
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getDoctorSlot throws AppointmentSlotNotFoundException when slot is not found with given doctorId and slotId', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // Then
    $this->expectExceptionObject(AppointmentSlotNotFoundException::withDoctorIdAndSlotId('some-doctor-id', 'some-slot-id'));

    // When
    $this->sut->getDoctorSlot('some-doctor-id', 'some-slot-id');
});

test('getDoctorSlot returns slot with given doctorId and slotId', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderMarketplaceAvailableSlotsResponse();

    // When
    $slot = $this->sut->getDoctorSlot(1, 1);

    // Then
    expect($slot)->getDateTime()->equalTo('2023-02-17 15:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(5000);
});

test('updateOrCreatePatient calls POST v1/subscription with patient data and subscriptionPartnerCode, using basic auth', function () {
    // Given
    $expectedPatientId = fakeDrConsultaProviderSubscriptionPatientResponse();
    $patient = new Patient(
        $this->faker->cpf(),
        $this->faker->name(),
        new Email($this->faker->safeEmail()),
        'M',
        now()->subYears(18)
    );

    // When
    $patientId = $this->sut->updateOrCreatePatient($patient);

    // Then
    expect($patientId)->toEqual($expectedPatientId);
    Http::assertSent(function (Request $request) use ($patient) {
        $data = $request->data();
        $bodyMatches = $patient->getCpf()->getNumber() === $data['cpf'] &&
            $patient->getCpf()->getNumber() === $data['matricula'] &&
            $patient->getName() === $data['nome'] &&
            $patient->getEmail() == $data['mail'] &&
            $patient->getGender() === $data['sexo'] &&
            $patient->getBirthDate()->toDateString() === $data['nasc'] &&
            $this->subscriptionPartnerCode === $data['codigo_parceiro'];
        $authMatches = $request->hasHeader('Authorization', 'Basic '.base64_encode($this->subscriptionUsername.':'.$this->subscriptionPassword));

        return $bodyMatches && $authMatches;
    });
});

test('schedule calls POST v1/agendamento with specialty, slotId, patientId and marketplaceDefaultUnitId', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    $expectedHash = fakeDrConsultaProviderMarketplaceScheduleResponse();

    // When
    $appointment = $this->sut->schedule('some-specialty', 'some-doctor-id', 'some-slot-id', 'some-patient-id');

    // Then
    expect($appointment)->getIdentifier()->toEqual($expectedHash);
    Http::assertSent(function (Request $request) {
        $data = $request->data();

        return $request->hasHeader('Authorization', 'Bearer some-token') &&
            'some-patient-id' === data_get($data, 'idPaciente') &&
            $this->marketplaceDefaultUnitId === data_get($data, 'idUnidade') &&
            'some-specialty' === data_get($data, 'idProduto') &&
            'some-slot-id' === data_get($data, 'idSlot');
    });
});