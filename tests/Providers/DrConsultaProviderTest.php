<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Providers\DrConsultaProvider;

beforeEach(function () {
    $this->clientBaseUrl = 'http://dr-consulta.url';
    $this->clientId = 'client-id';
    $this->secret = 'secret';
    $this->defaultUnitId = 1234;
    $this->sut = new DrConsultaProvider($this->clientBaseUrl, $this->clientId, $this->secret, $this->defaultUnitId);
});

function fakeDrConsultaProviderAuthentication(): void
{
    Http::fake([test()->clientBaseUrl.'/v1/login/auth' => Http::response(['access_token' => 'some-token'])]);
}

test('authenticate calls POST v1/login/auth with clientId and secret', function () {
    // Given
    Http::fake(["{$this->clientBaseUrl}/v1/login/auth" => Http::response(['access_token' => 'some-token'])]);

    // When
    $token = $this->sut->authenticate();

    // Then
    expect($token)->toEqual('some-token');
    Http::assertSent(function (Request $request) {
        return $request->data() === ['client_id' => $this->clientId, 'secret' => $this->secret];
    });
});

test('getDoctors returns an array of DrConsultaDoctor objects', function () {
    // Given
    fakeDrConsultaProviderAuthentication();
    Http::fake([
        "{$this->clientBaseUrl}/v1/profissional/slotsAtivos*" => Http::response(getFixtureAsJson('providers/drconsulta/doctors.json')),
    ]);

    // When
    $doctors = $this->sut->getDoctors();

    // Then
    expect($doctors)->each->toBeInstanceOf(Doctor::class)
        ->and($doctors->getItems()[0])->getId()->toEqual(1)
        ->getName()->toEqual('Doctor 1')
        ->getGender()->toEqual('F')
        ->getRating()->getValue()->toEqual(9.4)
        ->getRegistrationNumber()->toEqual('CRM-SP 12345')
        ->getPhoto()->toEqual("$this->clientBaseUrl/photos/1.jpg")
        ->and($doctors->getItems()[1])->getId()->toEqual(2)
        ->getName()->toEqual('Doctor 2')
        ->getGender()->toEqual('M')
        ->getRating()->getValue()->toEqual(9.8)
        ->getRegistrationNumber()->toEqual('CRM-SP 23456')
        ->getPhoto()->toBeNull();
    Http::assertSent(function (Request $request) {
        return $request->data() === ['idUnidade' => $this->defaultUnitId];
    });
});

test('getDoctors optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderAuthentication();
    Http::fake(["{$this->clientBaseUrl}/v1/profissional/slotsAtivos*" => Http::response([])]);

    // When
    $this->sut->getDoctors(1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->data() === ['idUnidade' => $this->defaultUnitId, 'idProduto' => '1234'];
    });
});

test('getSlotsForDoctor returns an array of DrConsultaAppointmentSlot objects', function () {
    // Given
    fakeDrConsultaProviderAuthentication();
    Http::fake([
        "{$this->clientBaseUrl}/v1/profissional/slotsAtivos*" => Http::response(getFixtureAsJson('providers/drconsulta/doctors.json')),
    ]);

    // When
    $slots = $this->sut->getSlotsForDoctor(2);

    // Then
    expect($slots)->each->toBeInstanceOf(AppointmentSlot::class)
        ->and($slots->getItems()[0])->getId()->toEqual(3)
        ->getDateTime()->equalTo('2023-02-18 16:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(6500)
        ->and($slots->getItems()[1])->getId()->toEqual(4)
        ->getDateTime()->equalTo('2023-02-19 16:00:00.000')->toBeTrue()
        ->getPrice()->getCents()->toEqual(6500);
    Http::assertSent(function (Request $request) {
        return $request->data() === ['idUnidade' => $this->defaultUnitId];
    });
});

test('getSlotsForDoctor optionally filters by specialty using idProduto parameter', function () {
    // Given
    fakeDrConsultaProviderAuthentication();
    Http::fake(["{$this->clientBaseUrl}/v1/profissional/slotsAtivos*" => Http::response([])]);

    // When
    $this->sut->getSlotsForDoctor(1, 1234);

    // Then
    Http::assertSent(function (Request $request) {
        return $request->data() === ['idUnidade' => $this->defaultUnitId, 'idProduto' => '1234'];
    });
});