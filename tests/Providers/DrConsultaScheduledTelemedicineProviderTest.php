<?php

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ValeSaude\LaravelValueObjects\Document;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\LaravelValueObjects\Enums\DocumentType;
use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\LaravelValueObjects\Gender;
use ValeSaude\LaravelValueObjects\Phone;
use ValeSaude\TelemedicineClient\Builders\PatientBuilder;
use ValeSaude\TelemedicineClient\Contracts\DrConsultaConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Contracts\SharedConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Entities\Appointment;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Entities\Patient;
use ValeSaude\TelemedicineClient\Providers\DrConsultaScheduledTelemedicineProvider;
use function PHPUnit\Framework\once;

beforeEach(function () {
    $this->marketplaceBaseUrl = 'http://dr-consulta.marketplace.url';
    $this->marketplaceDefaultUnitId = 1234;
    $this->healthPlanBaseUrl = 'http://dr-consulta.health-plan.url';
    $this->healthPlanContractId = 'health-plan-contract-id';
    $this->clientId = 'client-id';
    $this->secret = 'secret';

    $sharedConfigRepositoryMock = $this->createMock(SharedConfigRepositoryInterface::class);
    $sharedConfigRepositoryMock
        ->expects(once())
        ->method('getCache')
        ->willReturn($this->createMock(Repository::class));
    $drConsultaConfigRepositoryMock = $this->createMock(DrConsultaConfigRepositoryInterface::class);
    $drConsultaConfigRepositoryMock
        ->expects(once())
        ->method('getMarketplaceBaseUrl')
        ->willReturn($this->marketplaceBaseUrl);
    $drConsultaConfigRepositoryMock
        ->expects(once())
        ->method('getMarketplaceDefaultUnitId')
        ->willReturn($this->marketplaceDefaultUnitId);
    $drConsultaConfigRepositoryMock
        ->expects(once())
        ->method('getHealthPlanBaseUrl')
        ->willReturn($this->healthPlanBaseUrl);
    $drConsultaConfigRepositoryMock
        ->expects(once())
        ->method('getHealthPlanContractId')
        ->willReturn($this->healthPlanContractId);
    $drConsultaConfigRepositoryMock
        ->expects(once())
        ->method('getClientId')
        ->willReturn($this->clientId);
    $drConsultaConfigRepositoryMock
        ->expects(once())
        ->method('getSecret')
        ->willReturn($this->secret);

    $this->sut = new DrConsultaScheduledTelemedicineProvider(
        $sharedConfigRepositoryMock,
        $drConsultaConfigRepositoryMock
    );
});

function fakeDrConsultaProviderMarketplaceAuthenticationResponse(): void
{
    Http::fake([test()->marketplaceBaseUrl.'/v1/login/auth' => Http::response(['access_token' => 'marketplace-token'])]);
}

function fakeDrConsultaProviderHealthPlanAuthenticationResponse(): void
{
    Http::fake([test()->healthPlanBaseUrl.'/v1/login/auth' => Http::response(['access_token' => 'health-plan-token'])]);
}

function fakeDrConsultaProviderAvailableSlotsResponse(): void
{
    Http::fake([
        test()->marketplaceBaseUrl.'/v1/profissional/slotsAtivos*' => Http::response(getFixtureAsJson('providers/dr-consulta/doctors.json')),
    ]);
}

function fakeDrConsultaProviderScheduleResponse(): void
{
    Http::fake([
        test()->marketplaceBaseUrl.'/v1/agendamento' => Http::response(['hash' => 'appointment-hash']),
    ]);
}

function fakeDrConsultaProviderGetPatientNotFoundResponse(): void
{
    Http::fake([test()->healthPlanBaseUrl.'/v1/paciente/27740156507' => Http::response(null, 404)]);
}

function fakeDrConsultaProviderGetPatientResponse(): void
{
    Http::fake([
        test()->healthPlanBaseUrl.'/v1/paciente/27740156507' => Http::response(getFixtureAsJson('providers/dr-consulta/patient.json')),
    ]);
}

function fakeDrConsultaProviderUpdateOrCreatePatientResponse(): void
{
    Http::fake([
        test()->healthPlanBaseUrl.'/v1/matricula/subscription' => Http::response(getFixtureAsJson('providers/dr-consulta/patient.json')),
    ]);
}

function assertDrConsultaProviderActiveSlotsRequestedWithDefaultUnitId(): void
{
    Http::assertSent(static function (Request $request) {
        return $request->data() === ['idUnidade' => test()->marketplaceDefaultUnitId];
    });
}

function assertDrConsultaProviderRequestedWithMarketplaceToken(): void
{
    Http::assertSent(static function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer marketplace-token');
    });
}

function assertDrConsultaProviderRequestedWithHealthPlanToken(): void
{
    Http::assertSent(static function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer health-plan-token');
    });
}

function assertDrConsultaProviderUpdateOrCreatePatientRequestedWithExpectedParams(): void
{
    Http::assertSent(static function (Request $request) {
        $data = $request->data();

        return data_get($data, 'codigoContrato') === test()->healthPlanContractId &&
            data_get($data, 'nome') === 'Patient 1' &&
            data_get($data, 'cpf') === '27740156507' &&
            data_get($data, 'matricula') === '27740156507' &&
            data_get($data, 'dataNascimento') === '2000-01-01' &&
            data_get($data, 'sexo') === 'M' &&
            data_get($data, 'email') === '27740156507@27740156507.com' &&
            data_get($data, 'dddCelular') === '26' &&
            data_get($data, 'celular') === '666666666';
    });
}

function assertDrConsultaProviderScheduleRequestedWithExpectedParams(): void
{
    Http::assertSent(static function (Request $request) {
        $data = $request->data();

        return data_get($data, 'idPaciente') === 'patient-id' &&
            data_get($data, 'idUnidade') === test()->marketplaceDefaultUnitId &&
            data_get($data, 'idProfissional') === '1' &&
            data_get($data, 'idSlot') === '1';
    });
}

test('authenticateMarketplace calls POST v1/login/auth with clientId and secret', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();

    // When
    $token = $this->sut->authenticateMarketplace();

    // Then
    expect($token)->toEqual('marketplace-token');
    Http::assertSent(function (Request $request) {
        return $request->data() === ['client_id' => $this->clientId, 'secret' => $this->secret];
    });
});

test('authenticateHealthPlan calls POST v1/login/auth with clientId and secret', function () {
    // Given
    fakeDrConsultaProviderHealthPlanAuthenticationResponse();

    // When
    $token = $this->sut->authenticateHealthPlan();

    // Then
    expect($token)->toEqual('health-plan-token');
    Http::assertSent(function (Request $request) {
        return $request->data() === ['client_id' => $this->clientId, 'secret' => $this->secret];
    });
});

test('getDoctors returns a DoctorCollection', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

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
    assertDrConsultaProviderRequestedWithMarketplaceToken();
    assertDrConsultaProviderActiveSlotsRequestedWithDefaultUnitId();
});

test('getDoctors optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $this->sut->getDoctors(1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer marketplace-token') &&
            $request->data() === ['idUnidade' => $this->marketplaceDefaultUnitId, 'idProduto' => '1234'];
    });
});

test('getSlotsForDoctor returns a AppointmentSlotCollection', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $slots = $this->sut->getSlotsForDoctor(2);

    // Then
    expect($slots->at(0))->getId()->toEqual(3)
        ->getDateTime()->equalTo('2023-02-18 16:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(6500)
        ->and($slots->at(1))->getId()->toEqual(4)
        ->getDateTime()->equalTo('2023-02-19 16:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(6500);
    assertDrConsultaProviderRequestedWithMarketplaceToken();
    assertDrConsultaProviderActiveSlotsRequestedWithDefaultUnitId();
});

test('getSlotsForDoctor optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $this->sut->getSlotsForDoctor(1, 1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer marketplace-token') &&
            $request->data() === ['idUnidade' => $this->marketplaceDefaultUnitId, 'idProduto' => '1234'];
    });
});

test('getSlotsForDoctor optionally filters slots up to a given date/time', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $slots = $this->sut->getSlotsForDoctor(1, null, Carbon::make('2023-02-17 23:59:59'));

    // Then
    expect($slots)->toHaveCount(1)
        ->at(0)->getId()->toEqual(1);
    assertDrConsultaProviderRequestedWithMarketplaceToken();
    assertDrConsultaProviderActiveSlotsRequestedWithDefaultUnitId();
});

test('getDoctorsWithSlots returns a DoctorCollection with Doctor objects including slots property', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

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
    assertDrConsultaProviderRequestedWithMarketplaceToken();
    assertDrConsultaProviderActiveSlotsRequestedWithDefaultUnitId();
});

test('getDoctorsWithSlots optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $this->sut->getDoctorsWithSlots(1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer marketplace-token') &&
            $request->data() === ['idUnidade' => $this->marketplaceDefaultUnitId, 'idProduto' => '1234'];
    });
});

test('getDoctorsWithSlots optionally filters by doctor id', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $doctors = $this->sut->getDoctorsWithSlots(null, 1);

    // Then
    expect($doctors)->toHaveCount(1)
        ->and($doctors->at(0)->getSlots())->toHaveCount(2);
    assertDrConsultaProviderRequestedWithMarketplaceToken();
    assertDrConsultaProviderActiveSlotsRequestedWithDefaultUnitId();
});

test('getDoctorsWithSlots optionally filters doctor slots up to a given date/time', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $doctors = $this->sut->getDoctorsWithSlots(null, null, Carbon::make('2023-02-18 23:59:59'));

    // Then
    expect($doctors)->toHaveCount(2)
        ->and($doctors->at(0)->getSlots())->toHaveCount(2)
        ->at(0)->getId()->toEqual(1)
        ->at(1)->getId()->toEqual(2)
        ->and($doctors->at(1)->getSlots())->toHaveCount(1)
        ->at(0)->getId(3);
    assertDrConsultaProviderRequestedWithMarketplaceToken();
    assertDrConsultaProviderActiveSlotsRequestedWithDefaultUnitId();
});

test('getDoctorsWithSlots ignores doctors without slots up to given date', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $doctors = $this->sut->getDoctorsWithSlots(null, null, Carbon::make('2023-02-18 15:00:00.000'));

    // Then
    expect($doctors)->toHaveCount(1)
        ->and($doctors->at(0)->getSlots())->toHaveCount(2)
        ->at(0)->getId()->toEqual(1)
        ->at(1)->getId()->toEqual(2);
    assertDrConsultaProviderRequestedWithMarketplaceToken();
    assertDrConsultaProviderActiveSlotsRequestedWithDefaultUnitId();
});

test('getPatient returns null when the patient can not be found', function () {
    // Given
    fakeDrConsultaProviderHealthPlanAuthenticationResponse();
    fakeDrConsultaProviderGetPatientNotFoundResponse();

    // When
    $patient = $this->sut->getPatient('27740156507');

    // Then
    expect($patient)->toBeNull();
    assertDrConsultaProviderRequestedWithHealthPlanToken();
});

test('getPatient returns null when the patient is inactive', function () {
    // Given
    fakeDrConsultaProviderHealthPlanAuthenticationResponse();
    // FIXME: Confirmar nome de campo
    Http::fake([test()->healthPlanBaseUrl.'/v1/paciente/27740156507' => Http::response(['status' => 'N'])]);

    // When
    $patient = $this->sut->getPatient('27740156507');

    // Then
    expect($patient)->toBeNull();
    assertDrConsultaProviderRequestedWithHealthPlanToken();
});

test('getPatient a Patient instance on success', function () {
    // Given
    fakeDrConsultaProviderHealthPlanAuthenticationResponse();
    fakeDrConsultaProviderGetPatientResponse();

    // When
    $patient = $this->sut->getPatient('27740156507');

    // Then
    expect($patient)->toBeInstanceOf(Patient::class)
        ->getId()->toEqual('27740156507')
        ->getName()->toEqual('Patient 1')
        ->getDocument()->getNumber()->toEqual('27740156507')
        ->getDocument()->getType()->equals(DocumentType::CPF())->toBeTrue()
        ->getBirthDate()->toDateString()->toEqual('2000-01-01')
        ->getGender()->toEqual('M')
        ->getEmail()->toEqual('27740156507@27740156507.com')
        ->getPhone()->toEqual('26666666666');
    assertDrConsultaProviderRequestedWithHealthPlanToken();
});

test('updateOrCreatePatient creates and returns a Patient instance', function () {
    // Given
    fakeDrConsultaProviderHealthPlanAuthenticationResponse();
    fakeDrConsultaProviderUpdateOrCreatePatientResponse();
    $data = PatientBuilder::new()
        ->setName(FullName::fromFullNameString('Patient 1'))
        ->setDocument(Document::CPF('27740156507'))
        ->setBirthDate(Carbon::make('2000-01-01'))
        ->setGender(new Gender('M'))
        ->setEmail(new Email('27740156507@27740156507.com'))
        ->setPhone(new Phone('26666666666'))
        ->build();

    // When
    $patient = $this->sut->updateOrCreatePatient($data);

    // Then
    expect($patient)->toBeInstanceOf(Patient::class)
        ->getId()->toEqual('27740156507')
        ->getName()->toEqual('Patient 1')
        ->getDocument()->getNumber()->toEqual('27740156507')
        ->getDocument()->getType()->equals(DocumentType::CPF())->toBeTrue()
        ->getBirthDate()->toDateString()->toEqual('2000-01-01')
        ->getGender()->toEqual('M')
        ->getEmail()->toEqual('27740156507@27740156507.com')
        ->getPhone()->toEqual('26666666666');
    assertDrConsultaProviderRequestedWithHealthPlanToken();
    assertDrConsultaProviderUpdateOrCreatePatientRequestedWithExpectedParams();
});

test('schedule throws InvalidArgumentException when the real patient id cannot be resolved', function () {
    // Given
    fakeDrConsultaProviderHealthPlanAuthenticationResponse();
    fakeDrConsultaProviderGetPatientNotFoundResponse();

    // When
    $this->sut->schedule('27740156507', '1', '1');
})->throws(InvalidArgumentException::class, 'Invalid patient id.');

test('schedule returns an Appointment instance with the appointment identifier', function () {
    // Given
    fakeDrConsultaProviderHealthPlanAuthenticationResponse();
    fakeDrConsultaProviderGetPatientResponse();
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderScheduleResponse();

    // When
    $appointment = $this->sut->schedule('27740156507', '1', '1');

    // Then
    expect($appointment)->toBeInstanceOf(Appointment::class)
        ->getId()->toEqual('appointment-hash');
    assertDrConsultaProviderRequestedWithHealthPlanToken();
    assertDrConsultaProviderRequestedWithMarketplaceToken();
    assertDrConsultaProviderScheduleRequestedWithExpectedParams();
});
