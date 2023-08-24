<?php

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use ValeSaude\TelemedicineClient\Concerns\HasCacheHandlerTrait;
use function PHPUnit\Framework\callback;
use function PHPUnit\Framework\never;
use function PHPUnit\Framework\once;

beforeEach(function () {
    $this->cacheMock = $this->createMock(CacheRepository::class);
    $this->sut = new class ($this->cacheMock) {
        use HasCacheHandlerTrait;

        public function __construct(CacheRepository $cache)
        {
            $this->cache = $cache;
        }

        public function someCacheableMethod(): string
        {
            return $this->handlePossiblyCachedCall('some-cache-key', fn () => 'some possibly cached value');
        }
    };
});

it('returns value without using cache when cacheTtl has not been set', function () {
    // Given
    $this->cacheMock->expects(never())->method('remember');

    // When
    $value = $this->sut->someCacheableMethod();

    // Then
    expect($value)->toEqual('some possibly cached value');
});

it('returns cached value when cacheUntil is called', function () {
    // Given
    $this->cacheMock
        ->expects(once())
        ->method('remember')
        ->with(
            'some-cache-key',
            callback(fn (Carbon $ttl) => today()->addDay()->equalTo($ttl)),
            callback(fn (callable $callback) => $callback() === 'some possibly cached value')
        )
        ->willReturn('some surely cached value');

    // When
    $value = $this->sut
        ->cacheUntil(today()->addDay())
        ->someCacheableMethod();

    // Then
    expect($value)->toEqual('some surely cached value');
});

it('returns value without using cache when withoutCache is called', function () {
    // Given
    $this->cacheMock->expects(never())->method('remember');

    // When
    $value = $this->sut
        ->cacheUntil(today()->addDay())
        ->withoutCache()
        ->someCacheableMethod();

    // Then
    expect($value)->toEqual('some possibly cached value');
});