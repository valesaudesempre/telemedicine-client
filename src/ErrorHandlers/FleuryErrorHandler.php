<?php

namespace ValeSaude\TelemedicineClient\ErrorHandlers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use ValeSaude\TelemedicineClient\Contracts\ProviderErrorHandlerInterface;
use ValeSaude\TelemedicineClient\Exceptions\GenericRequestException;

class FleuryErrorHandler implements ProviderErrorHandlerInterface
{
    public function handleErrors(Response $response): void
    {
        if (!$response->failed()) {
            return;
        }

        $message = $response->json('message');
        /** @var RequestException $originalException */
        $originalException = $response->toException();

        throw new GenericRequestException($originalException, $message);
    }
}