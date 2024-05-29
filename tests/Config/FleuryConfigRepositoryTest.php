<?php

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use ValeSaude\TelemedicineClient\Config\FleuryConfigRepository;
use function PHPUnit\Framework\once;

beforeEach(function () {
    $this->configRepositoryMock = $this->createMock(ConfigRepository::class);
    $this->sut = new FleuryConfigRepository($this->configRepositoryMock);
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
    'getApiKey',
    'getClientId',
    'getWebhookToken',
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
    'base_url' => [
        'getBaseUrl',
        'services.fleury.base_url',
        'http://provider.url',
    ],
    'api_key' => [
        'getApiKey',
        'services.fleury.api_key',
        '1234',
    ],
    'client_id' => [
        'getClientId',
        'services.fleury.client_id',
        '1234',
    ],
    'webhook_token' => [
        'getWebhookToken',
        'services.fleury.webhook_token',
        '1234',
    ],
]);