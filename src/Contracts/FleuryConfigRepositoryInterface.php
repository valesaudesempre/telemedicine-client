<?php

namespace ValeSaude\TelemedicineClient\Contracts;

interface FleuryConfigRepositoryInterface
{
    public function getBaseUrl(): string;

    public function getApiKey(): string;

    public function getClientId(): string;
}