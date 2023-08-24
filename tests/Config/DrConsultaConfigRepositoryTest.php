<?php

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use ValeSaude\TelemedicineClient\Config\DrConsultaConfigRepository;
use function PHPUnit\Framework\once;

beforeEach(function () {
    $this->configRepositoryMock = $this->createMock(ConfigRepository::class);
    $this->sut = new DrConsultaConfigRepository($this->configRepositoryMock);
});

it('throws when config value is not set and has no default value', function (string $method) {
    // Given
    $this->configRepositoryMock
        ->expects(once())
        ->method('get')
        ->willReturn(null);

    // When
    $this->sut->{$method}();
})->with([
    'getMarketplaceDefaultUnitId',
    'getHealthPlanContractId',
    'getClientId',
    'getSecret',
])->throws(RuntimeException::class);

it('returns expected config value for each method', function (string $method, string $path, string $expectedValue) {
    // Given
    $this->configRepositoryMock
        ->expects(once())
        ->method('get')
        ->with($path)
        ->willReturn($expectedValue);

    // When
    $value = $this->sut->{$method}('provider');

    // Then
    expect($value)->toEqual($expectedValue);
})->with([
    'marketplace_base_url' => [
        'getMarketplaceBaseUrl',
        'services.dr-consulta.marketplace_base_url',
        'http://provider.marketplace.url',
    ],
    'marketplace_default_unit_id' => [
        'getMarketplaceDefaultUnitId',
        'services.dr-consulta.marketplace_default_unit_id',
        '1234',
    ],
    'health_plan_base_url' => [
        'getHealthPlanBaseUrl',
        'services.dr-consulta.health_plan_base_url',
        'http://provider.health-plan.url',
    ],
    'health_plan_contract_id' => [
        'getHealthPlanContractId',
        'services.dr-consulta.health_plan_contract_id',
        'some-contract-id',
    ],
    'client_id' => [
        'getClientId',
        'services.dr-consulta.client_id',
        'client-id',
    ],
    'secret' => [
        'getSecret',
        'services.dr-consulta.secret',
        'secret',
    ],
]);