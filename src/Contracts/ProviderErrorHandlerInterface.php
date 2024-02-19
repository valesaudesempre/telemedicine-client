<?php

namespace ValeSaude\TelemedicineClient\Contracts;

use Illuminate\Http\Client\Response;
use ValeSaude\TelemedicineClient\Exceptions\GenericRequestException;

interface ProviderErrorHandlerInterface
{
    /**
     * @throws GenericRequestException
     */
    public function handleErrors(Response $response): void;
}