<?php

namespace ValeSaude\TelemedicineClient\Authentication;

use InvalidArgumentException;
use ValeSaude\TelemedicineClient\Contracts\AuthenticationTokenInterface;

/**
 * @phpstan-type FleuryAuthenticationTokenResponse=array{access_token: string, expires_in: int}
 */
class FleuryAuthenticationToken implements AuthenticationTokenInterface
{
    private const THRESHOLD_IN_SECONDS = 60;

    /** @var FleuryAuthenticationTokenResponse */
    private array $response;
    private int $issuedAt;

    /**
     * @param FleuryAuthenticationTokenResponse $response
     */
    public function __construct(array $response, ?int $issuedAt = null)
    {
        $this->ensureRequiredPropertiesAreSet($response);

        $this->response = $response;
        $this->issuedAt = $issuedAt ?? time();
    }

    public function getAccessToken(): string
    {
        return $this->response['access_token'];
    }

    public function getExpiresIn(): int
    {
        return $this->response['expires_in'];
    }

    public function getExpiresAt(): int
    {
        return $this->issuedAt + $this->getExpiresIn() - self::THRESHOLD_IN_SECONDS;
    }

    public function isValid(): bool
    {
        return time() < $this->getExpiresAt();
    }

    /**
     * @param FleuryAuthenticationTokenResponse $response
     */
    private function ensureRequiredPropertiesAreSet(array $response): void
    {
        if (!isset($response['access_token'])) {
            throw new InvalidArgumentException('The access_token attribute is required');
        }

        if (!isset($response['expires_in'])) {
            throw new InvalidArgumentException('The expires_in attribute is required');
        }
    }
}