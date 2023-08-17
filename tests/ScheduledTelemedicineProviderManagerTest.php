<?php

use Mockery\MockInterface;
use ValeSaude\TelemedicineClient\Contracts\SharedConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\ScheduledTelemedicineProviderManager;
use ValeSaude\TelemedicineClient\Testing\FakeScheduledTelemedicineProvider;
use ValeSaude\TelemedicineClient\Tests\Dummies\DummyScheduledTelemedicineProvider;

beforeEach(function () {
    $this->sharedConfigRepositoryMock = $this->createMock(SharedConfigRepositoryInterface::class);
    $this->sharedConfigRepositoryMock
        ->method('getScheduledTelemedicineProviderClass')
        ->with('dummy')
        ->willReturn(DummyScheduledTelemedicineProvider::class);
    $this->instance(SharedConfigRepositoryInterface::class, $this->sharedConfigRepositoryMock);
    $this->sut = ScheduledTelemedicineProviderManager::getInstance();
});

afterEach(function () {
    $this->sut->clearInstance();
});

test('resolve returns provider matching given slug', function () {
    // When
    $provider = $this->sut->resolve('dummy');

    // Then
    expect($provider)->toBeInstanceOf(DummyScheduledTelemedicineProvider::class);
});

test('fake returns FakeScheduledTelemedicineProvider instance, replacing resolved provider instance', function () {
    // Given
    $previouslyResolvedProvider = $this->sut->resolve('dummy');

    // When
    $fakeProvider = $this->sut->fake('dummy');
    $resolvedProvider = $this->sut->resolve('dummy');

    // Then
    expect($previouslyResolvedProvider)->toBeInstanceOf(DummyScheduledTelemedicineProvider::class)
        ->and($fakeProvider)->toBeInstanceOf(FakeScheduledTelemedicineProvider::class)
        ->and($resolvedProvider)->toBe($fakeProvider);
});

test('mock returns MockInterface instance, replacing resolved client instance', function () {
    // Given
    $previouslyResolvedProvider = $this->sut->resolve('dummy');

    // When
    $mockProvider = $this->sut->mock('dummy');
    $resolvedProvider = $this->sut->resolve('dummy');

    // Then
    expect($previouslyResolvedProvider)->toBeInstanceOf(DummyScheduledTelemedicineProvider::class)
        ->and($mockProvider)->toBeInstanceOf(MockInterface::class)
        ->and($resolvedProvider)->toBe($mockProvider);
});

test('partialMock method returns mock instance made partial', function () {
    // When
    $mockClient = $this->sut->partialMock('dummy');

    // Then
    expect($mockClient)->toBeInstanceOf(MockInterface::class);
});

