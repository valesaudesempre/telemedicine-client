<?php

namespace ValeSaude\TelemedicineClient\Contracts;

interface AuthenticationTokenInterface
{
    public function getAccessToken(): string;

    public function isValid(): bool;
}