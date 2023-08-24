<?php

namespace ValeSaude\TelemedicineClient\Config;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;
use ValeSaude\TelemedicineClient\Contracts\SharedConfigRepositoryInterface;

class SharedConfigRepository implements SharedConfigRepositoryInterface
{
    private ConfigRepository $config;
    private Factory $cacheFactory;

    public function __construct(ConfigRepository $config, Factory $cacheFactory)
    {
        $this->config = $config;
        $this->cacheFactory = $cacheFactory;
    }

    public function getScheduledTelemedicineProviderClass(string $provider): string
    {
        $class = $this->config->get("telemedicine-client.scheduled-telemedicine.providers.{$provider}");

        if (!isset($class)) {
            throw new RuntimeException("Unable to resolve provider identified by \"{$provider}\".");
        }

        return $class;
    }

    public function getCache(): CacheRepository
    {
        return $this->cacheFactory->store($this->config->get('telemedicine-client.cache-store'));
    }
}