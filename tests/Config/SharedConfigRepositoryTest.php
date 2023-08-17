<?php

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use ValeSaude\TelemedicineClient\Config\SharedConfigRepository;
use function PHPUnit\Framework\once;

beforeEach(function () {
    $this->configRepositoryMock = $this->createMock(ConfigRepository::class);
    $this->cacheFactoryMock = $this->createMock(Factory::class);
    $this->sut = new SharedConfigRepository($this->configRepositoryMock, $this->cacheFactoryMock);
});

test('getScheduledTelemedicineProviderClass throws when the provider is not set', function () {
    // Given
    $this->configRepositoryMock
        ->expects(once())
        ->method('get')
        ->with('telemedicine-client.scheduled-telemedicine.providers.some-provider')
        ->willReturn(null);

    // When
    $this->sut->getScheduledTelemedicineProviderClass('some-provider');
})->throws(RuntimeException::class, 'Unable to resolve provider identified by "some-provider".');

test('getScheduledTelemedicineProviderClass returns the specified provider fqcn', function () {
    // Given
    $this->configRepositoryMock
        ->expects(once())
        ->method('get')
        ->with('telemedicine-client.scheduled-telemedicine.providers.some-provider')
        ->willReturn('Some\\Scheduled\\Provider');

    // When
    $class = $this->sut->getScheduledTelemedicineProviderClass('some-provider');

    // Then
    expect($class)->toBe('Some\\Scheduled\\Provider');
});

test('getCache returns the specified cache store instance', function () {
    // Given
    $cacheRepositoryMock = $this->createMock(CacheRepository::class);
    $this->configRepositoryMock
        ->expects(once())
        ->method('get')
        ->with('telemedicine-client.cache-store')
        ->willReturn('some-cache-store');
    $this->cacheFactoryMock
        ->expects(once())
        ->method('store')
        ->with('some-cache-store')
        ->willReturn($cacheRepositoryMock);

    // When
    $cache = $this->sut->getCache();

    // Then
    expect($cache)->toBe($cacheRepositoryMock);
});