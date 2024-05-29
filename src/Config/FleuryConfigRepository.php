<?php

namespace ValeSaude\TelemedicineClient\Config;

use Illuminate\Contracts\Config\Repository;
use RuntimeException;
use ValeSaude\TelemedicineClient\Contracts\FleuryConfigRepositoryInterface;

class FleuryConfigRepository implements FleuryConfigRepositoryInterface
{
    private Repository $config;

    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    public function getBaseUrl(): string
    {
        return $this->config->get('services.fleury.base_url', 'https://api-hml.grupofleury.com.br');
    }

    public function getApiKey(): string
    {
        $apiKey = $this->config->get('services.fleury.api_key');

        if (!isset($apiKey)) {
            throw new RuntimeException('Unable to resolve api key.');
        }

        return $apiKey;
    }

    public function getClientId(): string
    {
        $clientId = $this->config->get('services.fleury.client_id');

        if (!isset($clientId)) {
            throw new RuntimeException('Unable to resolve client id.');
        }

        return $clientId;
    }

    public function getWebhookToken(): string
    {
        $clientId = $this->config->get('services.fleury.webhook_token');

        if (!isset($clientId)) {
            throw new RuntimeException('Unable to resolve client id.');
        }

        return $clientId;
    }
}
