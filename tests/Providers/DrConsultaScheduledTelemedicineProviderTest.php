<?php

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ValeSaude\TelemedicineClient\Contracts\DrConsultaConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Contracts\SharedConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Providers\DrConsultaScheduledTelemedicineProvider;
use function PHPUnit\Framework\once;

beforeEach(function () {
    $this->marketplaceBaseUrl = 'http://dr-consulta.marketplace.url';
    $this->marketplaceDefaultUnitId = 1234;
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
    Http::fake([test()->marketplaceBaseUrl.'/v1/login/auth' => Http::response(['access_token' => 'some-token'])]);
}

function fakeDrConsultaProviderAvailableSlotsResponse(): void
{
    Http::fake([
        test()->marketplaceBaseUrl.'/v1/profissional/slotsAtivos*' => Http::response(getFixtureAsJson('providers/dr-consulta/doctors.json')),
    ]);
}

function assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken(): void
{
    Http::assertSent(static function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer some-token') &&
            $request->data() === ['idUnidade' => test()->marketplaceDefaultUnitId];
    });
}

test('authenticateMarketplace calls POST v1/login/auth with clientId and secret', function () {
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
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getDoctors optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

    // When
    $this->sut->getDoctors(1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer some-token') &&
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
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getSlotsForDoctor optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

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
    fakeDrConsultaProviderAvailableSlotsResponse();

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
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});

test('getDoctorsWithSlots optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderMarketplaceAuthenticationResponse();
    fakeDrConsultaProviderAvailableSlotsResponse();

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
    fakeDrConsultaProviderAvailableSlotsResponse();

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
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
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
    assertDrConsultaActiveSlotsRequestedWithDefaultUnitIdAndToken();
});