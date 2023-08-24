<?php

namespace ValeSaude\TelemedicineClient\Config;

use Illuminate\Contracts\Config\Repository;
use RuntimeException;
use ValeSaude\TelemedicineClient\Contracts\DrConsultaConfigRepositoryInterface;

class DrConsultaConfigRepository implements DrConsultaConfigRepositoryInterface
{
    private Repository $config;

    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    public function getMarketplaceBaseUrl(): string
    {
        return $this->config->get('services.dr-consulta.marketplace_base_url', 'https://b2bmarketplaceapihomolog.drconsulta.com');
    }

    public function getMarketplaceDefaultUnitId(): int
    {
        $defaultUnitId = $this->config->get('services.dr-consulta.marketplace_default_unit_id');

        if (!isset($defaultUnitId)) {
            throw new RuntimeException('Unable to resolve marketplace default unit id.');
        }

        return $defaultUnitId;
    }

    public function getHealthPlanBaseUrl(): string
    {
        return $this->config->get('services.dr-consulta.health_plan_base_url', 'https://b2bconveniosapihomolog.drconsulta.com');
    }

    public function getHealthPlanContractId(): string
    {
        $healthPlanContractId = $this->config->get('services.dr-consulta.health_plan_contract_id');

        if (!isset($healthPlanContractId)) {
            throw new RuntimeException('Unable to resolve health plan contract id.');
        }

        return $healthPlanContractId;
    }

    public function getClientId(): string
    {
        $clientId = $this->config->get('services.dr-consulta.client_id');

        if (!isset($clientId)) {
            throw new RuntimeException('Unable to resolve client id.');
        }

        return $clientId;
    }

    public function getSecret(): string
    {
        $secret = $this->config->get('services.dr-consulta.secret');

        if (!isset($secret)) {
            throw new RuntimeException('Unable to resolve secret.');
        }

        return $secret;
    }
}