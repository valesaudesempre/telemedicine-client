<?php

namespace ValeSaude\TelemedicineClient;

use Illuminate\Contracts\Container\Container;
use Mockery;
use Mockery\MockInterface;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Contracts\SharedConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Testing\FakeScheduledTelemedicineProvider;

class ScheduledTelemedicineProviderManager
{
    /** @var array<string, ScheduledTelemedicineProviderInterface> */
    private array $resolvedProviderInstances = [];
    private Container $container;
    private SharedConfigRepositoryInterface $config;
    private static ?self $instance;

    private function __construct(Container $container, SharedConfigRepositoryInterface $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    public function resolve(string $providerSlug): ScheduledTelemedicineProviderInterface
    {
        if ($instance = $this->resolvedProviderInstances[$providerSlug] ?? null) {
            return $instance;
        }

        $provider = $this->container->make($this->config->getScheduledTelemedicineProviderClass($providerSlug));
        $this->swap($providerSlug, $provider);

        return $provider;
    }

    public function fake(string $providerSlug): FakeScheduledTelemedicineProvider
    {
        $provider = new FakeScheduledTelemedicineProvider();
        $this->swap($providerSlug, $provider);

        return $provider;
    }

    /**
     * @return MockInterface&FakeScheduledTelemedicineProvider
     */
    public function mock(string $providerSlug): MockInterface
    {
        /** @var MockInterface&FakeScheduledTelemedicineProvider $provider */
        $provider = Mockery::mock(FakeScheduledTelemedicineProvider::class);
        $this->swap($providerSlug, $provider);

        return $provider;
    }

    /**
     * @return MockInterface&FakeScheduledTelemedicineProvider
     */
    public function partialMock(string $providerSlug): FakeScheduledTelemedicineProvider
    {
        $fake = new FakeScheduledTelemedicineProvider();
        /** @var MockInterface&FakeScheduledTelemedicineProvider $provider */
        $provider = Mockery::mock($fake)->makePartial();
        $this->swap($providerSlug, $provider);

        return $provider;
    }

    public function swap(string $providerSlug, ScheduledTelemedicineProviderInterface $instance): void
    {
        $this->resolvedProviderInstances[$providerSlug] = $instance;
    }

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = self::newInstance();
        }

        return self::$instance;
    }

    /**
     * @internal For testing purposes only.
     */
    public static function clearInstance(): void
    {
        self::$instance = null;
    }

    private static function newInstance(): self
    {
        $container = resolve(Container::class);

        return new self(
            $container,
            $container->make(SharedConfigRepositoryInterface::class)
        );
    }
}