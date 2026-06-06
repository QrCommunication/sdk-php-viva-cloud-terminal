<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Tests\Fakes;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use QrCommunication\VivaCloudTerminal\CloudTerminalConfig;
use QrCommunication\VivaCloudTerminal\HttpClient;

/**
 * Factory that builds a real HttpClient with a Guzzle MockHandler injected
 * via the optional $guzzle parameter of HttpClient::__construct().
 *
 * Usage:
 *
 *     [$http, $mockHandler] = GuzzleMockFactory::create($config);
 *     $mockHandler->append(GuzzleMockFactory::tokenResponse());           // OAuth handshake
 *     $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [...]));  // endpoint response
 *     $result = $http->post('/ecr/v1/transactions:sale', [...]);
 */
final class GuzzleMockFactory
{
    /**
     * @return array{0: HttpClient, 1: MockHandler}
     */
    public static function create(CloudTerminalConfig $config): array
    {
        $mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($mockHandler);
        $guzzleClient = new Client([
            'handler' => $handlerStack,
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $http = new HttpClient($config, $guzzleClient);

        return [$http, $mockHandler];
    }

    /**
     * Build a JSON response for the mock handler.
     *
     * @param  array<int|string, mixed>  $body
     */
    public static function jsonResponse(int $status, array $body): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Build a raw response with an arbitrary (possibly empty) body. Useful for
     * Cloud Terminal endpoints that return HTTP 200 with no body.
     */
    public static function rawResponse(int $status, string $body = ''): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }

    /**
     * Build the OAuth2 token response that the HttpClient fetches before any
     * Bearer request. Append this BEFORE the actual endpoint response.
     */
    public static function tokenResponse(string $accessToken = 'test-access-token', int $expiresIn = 3600): Response
    {
        return self::jsonResponse(200, [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'scope' => 'urn:viva:payments:ecr:api',
        ]);
    }

    /**
     * Build an error JSON response matching Viva's error format.
     */
    public static function errorResponse(int $status, string $errorText, int $errorCode = 0): Response
    {
        return self::jsonResponse($status, [
            'ErrorText' => $errorText,
            'ErrorCode' => $errorCode,
        ]);
    }
}
