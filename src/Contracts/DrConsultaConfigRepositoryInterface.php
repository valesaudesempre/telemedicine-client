<?php

namespace ValeSaude\TelemedicineClient\Contracts;

interface DrConsultaConfigRepositoryInterface
{
    public function getMarketplaceBaseUrl(): string;

    public function getMarketplaceDefaultUnitId(): int;

    public function getHealthPlanBaseUrl(): string;

    public function getHealthPlanContractId(): string;

    public function getClientId(): string;

    public function getSecret(): string;
}