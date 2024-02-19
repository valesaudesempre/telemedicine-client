<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use ValeSaude\TelemedicineClient\ErrorHandlers\FleuryErrorHandler;
use ValeSaude\TelemedicineClient\Exceptions\GenericRequestException;
use function PHPUnit\Framework\never;
use function PHPUnit\Framework\once;

beforeEach(function () {
    $this->sut = new FleuryErrorHandler();
});

it('does nothing when the response is successful', function () {
    // Given
    $response = $this->createMock(Response::class);
    $response->expects(once())
        ->method('failed')
        ->willReturn(false);
    $response->expects(never())
        ->method('json');

    // When
    $this->sut->handleErrors($response);
});

it('throws a GenericRequestException when the response is not successful', function (array $body) {
    // Given
    /** @var string $json */
    $json = json_encode($body);
    $psrResponse = new Psr7Response(400, ['Content-Type' => 'application/json'], $json);
    $response = new Response($psrResponse);

    // When
    $this->sut->handleErrors($response);
})->with([
    'response with message field' => fn () => ['message' => 'Some error message'],
    'response without message field' => fn () => [],
])->throws(GenericRequestException::class);