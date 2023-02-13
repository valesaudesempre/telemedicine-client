<?php

use Illuminate\Contracts\Container\BindingResolutionException;
use Mockery\MockInterface;
use ValeSaude\TelemedicineClient\ScheduledTelemedicineProviderManager;
use ValeSaude\TelemedicineClient\Testing\FakeScheduledTelemedicineProvider;
use ValeSaude\TelemedicineClient\Tests\Dummies\DummyScheduledTelemedicineProvider;

beforeEach(function () {
    config()->set('telemedicine-client.scheduled-telemedicine.providers.dummy', DummyScheduledTelemedicineProvider::class);
});

afterEach(function () {
    ScheduledTelemedicineProviderManager::clearResolvedInstances();
});

test('resolve throws when slug is invalid', function () {
    ScheduledTelemedicineProviderManager::resolve('invalid-slug');
})->throws(BindingResolutionException::class);

test('resolve returns provider matching given slug', function () {
    // When
    $provider = ScheduledTelemedicineProviderManager::resolve('dummy');

    // Then
    expect($provider)->toBeInstanceOf(DummyScheduledTelemedicineProvider::class);
});

test('fake returns FakeScheduledTelemedicineProvider instance, replacing resolved provider instance', function () {
    // Given
    $previouslyResolvedProvider = ScheduledTelemedicineProviderManager::resolve('dummy');

    // When
    $fakeProvider = ScheduledTelemedicineProviderManager::fake('dummy');
    $resolvedProvider = ScheduledTelemedicineProviderManager::resolve('dummy');

    // Then
    expect($previouslyResolvedProvider)->toBeInstanceOf(DummyScheduledTelemedicineProvider::class)
        ->and($fakeProvider)->toBeInstanceOf(FakeScheduledTelemedicineProvider::class)
        ->and($resolvedProvider)->toBe($fakeProvider);
});

test('mock returns MockInterface instance, replacing resolved client instance', function () {
    // Given
    $previouslyResolvedProvider = ScheduledTelemedicineProviderManager::resolve('dummy');

    // When
    $mockProvider = ScheduledTelemedicineProviderManager::mock('dummy');
    $resolvedProvider = ScheduledTelemedicineProviderManager::resolve('dummy');

    // Then
    expect($previouslyResolvedProvider)->toBeInstanceOf(DummyScheduledTelemedicineProvider::class)
        ->and($mockProvider)->toBeInstanceOf(MockInterface::class)
        ->and($resolvedProvider)->toBe($mockProvider);
});

test('partialMock method returns mock instance made partial', function () {
    // When
    $mockClient = ScheduledTelemedicineProviderManager::partialMock('dummy');

    // Then
    expect($mockClient)->toBeInstanceOf(MockInterface::class);
});

