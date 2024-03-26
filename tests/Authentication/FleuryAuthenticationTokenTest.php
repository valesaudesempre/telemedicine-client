<?php

namespace ValeSaude\TelemedicineClient\Tests\Helpers;

use InvalidArgumentException;
use ValeSaude\TelemedicineClient\Authentication\FleuryAuthenticationToken;

beforeEach(function () {
    $this->sut = new FleuryAuthenticationToken(['access_token' => 'token', 'expires_in' => 86400]);
});

test('throws InvalidArgumentException when provided response does not contain the required attributes', function (array $response, InvalidArgumentException $expectedException) {
    $this->expectExceptionObject($expectedException);

    new FleuryAuthenticationToken($response);
})->with([
    'response without access_token' => [
        ['expires_in' => 3600],
        fn () => new InvalidArgumentException('The access_token attribute is required'),
    ],
    'response without expires_in' => [
        ['access_token' => 'token'],
        fn () => new InvalidArgumentException('The expires_in attribute is required'),
    ],
]);

test('getAccessToken returns the provided access token', function () {
    expect($this->sut->getAccessToken())->toBe('token');
});

test('getExpiresIn returns the provided expires in value', function () {
    expect($this->sut->getExpiresIn())->toBe(86400);
});

test('getExpiresAt returns the timestamp when the token expires applying the threshold', function () {
    // Given
    $issuedAt = 100000;
    $token = new FleuryAuthenticationToken(['access_token' => 'token', 'expires_in' => 86400], $issuedAt);

    // Then
    expect($token->getExpiresAt())->toBe(186340);
});

test('isValid returns a boolean whether the token is still valid based on its expiration', function (FleuryAuthenticationToken $token, bool $expected) {
    // Given
    $token = new FleuryAuthenticationToken(['access_token' => 'token', 'expires_in' => 62]);

    // Then
    expect($token->isValid())->toBeTrue();
})->with([
    'valid token' => [
        fn () => new FleuryAuthenticationToken(['access_token' => 'token', 'expires_in' => 62]),
        true,
    ],
    'expired token' => [
        fn () => new FleuryAuthenticationToken(['access_token' => 'token', 'expires_in' => 60]),
        false,
    ],
]);

