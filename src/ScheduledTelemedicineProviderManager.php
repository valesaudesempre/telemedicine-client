<?php

namespace ValeSaude\TelemedicineClient;

use Illuminate\Contracts\Container\BindingResolutionException;
use Mockery;
use Mockery\MockInterface;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Testing\FakeScheduledTelemedicineProvider;

class ScheduledTelemedicineProviderManager
{
    /** @var array<string, ScheduledTelemedicineProviderInterface> */
    private static array $instances = [];

    public static function resolve(string $providerSlug): ScheduledTelemedicineProviderInterface
    {
        if ($instance = self::$instances[$providerSlug] ?? null) {
            return $instance;
        }

        $class = config("telemedicine-client.scheduled-telemedicine.providers.{$providerSlug}");
        if (!isset($class)) {
            throw new BindingResolutionException("Unable to resolve gateway identified by \"{$providerSlug}\".");
        }

        $provider = resolve($class);
        self::swap($providerSlug, $provider);

        return $provider;
    }

    public static function fake(string $providerSlug): FakeScheduledTelemedicineProvider
    {
        $provider = new FakeScheduledTelemedicineProvider();
        self::swap($providerSlug, $provider);

        return $provider;
    }

    /**
     * @return MockInterface&FakeScheduledTelemedicineProvider
     */
    public static function mock(string $providerSlug): MockInterface
    {
        /** @var MockInterface&FakeScheduledTelemedicineProvider $provider */
        $provider = Mockery::mock(FakeScheduledTelemedicineProvider::class);
        self::swap($providerSlug, $provider);

        return $provider;
    }

    /**
     * @return MockInterface&FakeScheduledTelemedicineProvider
     */
    public static function partialMock(string $providerSlug): FakeScheduledTelemedicineProvider
    {
        $fake = new FakeScheduledTelemedicineProvider();
        /** @var MockInterface&FakeScheduledTelemedicineProvider $provider */
        $provider = Mockery::mock($fake)->makePartial();
        self::swap($providerSlug, $provider);

        return $provider;
    }

    public static function swap(string $providerSlug, ScheduledTelemedicineProviderInterface $instance): void
    {
        self::$instances[$providerSlug] = $instance;
    }

    public static function clearResolvedInstances(): void
    {
        self::$instances = [];
    }
}