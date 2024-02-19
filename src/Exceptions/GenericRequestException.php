<?php

namespace ValeSaude\TelemedicineClient\Exceptions;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * @method RequestException getPrevious()
 *
 * @codeCoverageIgnore
 */
class GenericRequestException extends RuntimeException
{
    public function __construct(RequestException $e, ?string $message = null)
    {
        parent::__construct(
            $message
                ? "The service responded with an error: {$message}"
                : 'An unexpected error occurred while processing the request.',
            $e->getCode(),
            $e
        );
    }

    public function getResponse(): Response
    {
        return $this->getPrevious()->response;
    }
}