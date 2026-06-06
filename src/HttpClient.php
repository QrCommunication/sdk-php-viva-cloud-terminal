<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use QrCommunication\VivaCloudTerminal\Exceptions\ApiException;
use QrCommunication\VivaCloudTerminal\Exceptions\AuthenticationException;

/**
 * Low-level HTTP client for the Cloud Terminal API.
 *
 * Single auth mode: OAuth2 client_credentials → Bearer token.
 * - Token endpoint: accounts.vivapayments.com/connect/token (Basic Auth client_id:client_secret)
 * - API calls:      api.vivapayments.com/ecr/v1/...          (Authorization: Bearer {token})
 *
 * Tokens are cached in-memory and refreshed automatically 60s before expiry.
 */
final class HttpClient
{
    private ?string $accessToken = null;

    private ?float $tokenExpiresAt = null;

    private readonly Client $guzzle;

    public function __construct(
        private readonly CloudTerminalConfig $config,
        ?Client $guzzle = null,
    ) {
        $this->guzzle = $guzzle ?? new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->requestBearer('GET', $this->config->apiUrl().$path, [
            RequestOptions::QUERY => $query,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body = []): array
    {
        return $this->requestBearer('POST', $this->config->apiUrl().$path, [
            RequestOptions::JSON => $body,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function delete(string $path, array $query = []): array
    {
        return $this->requestBearer('DELETE', $this->config->apiUrl().$path, [
            RequestOptions::QUERY => $query,
        ]);
    }

    /**
     * Drop the cached access token, forcing a fresh OAuth handshake on the next call.
     */
    public function invalidateToken(): void
    {
        $this->accessToken = null;
        $this->tokenExpiresAt = null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function requestBearer(string $method, string $url, array $options = []): array
    {
        $this->authenticate();

        $options[RequestOptions::HEADERS] = array_merge(
            $options[RequestOptions::HEADERS] ?? [],
            ['Authorization' => 'Bearer '.$this->accessToken],
        );

        return $this->doRequest($method, $url, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function doRequest(string $method, string $url, array $options = []): array
    {
        try {
            $response = $this->guzzle->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new ApiException("HTTP request failed: {$e->getMessage()}", 0, null, $e);
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        // Many Cloud Terminal endpoints return an empty body on success
        // (e.g. transactions:sale 200, actions GET 202 while processing).
        if ($body === '' && $statusCode >= 200 && $statusCode < 300) {
            return [];
        }

        $decoded = json_decode($body, true) ?? [];

        if ($statusCode >= 400) {
            $message = $decoded['ErrorText']
                ?? $decoded['message']
                ?? $decoded['detail']
                ?? $decoded['Message']
                ?? "HTTP {$statusCode}";

            throw new ApiException($message, $statusCode, $decoded);
        }

        return $decoded;
    }

    private function authenticate(): void
    {
        if ($this->accessToken && $this->tokenExpiresAt && microtime(true) < $this->tokenExpiresAt) {
            return;
        }

        try {
            $response = $this->guzzle->post(
                $this->config->accountsUrl().'/connect/token',
                [
                    RequestOptions::AUTH => [$this->config->clientId, $this->config->clientSecret],
                    RequestOptions::FORM_PARAMS => ['grant_type' => 'client_credentials'],
                    RequestOptions::HEADERS => ['Content-Type' => 'application/x-www-form-urlencoded'],
                ],
            );
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Cloud Terminal token request failed: '.$e->getMessage(), $e);
        }

        $statusCode = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), true) ?? [];

        if ($statusCode !== 200 || empty($data['access_token'])) {
            throw new AuthenticationException(
                'Cloud Terminal OAuth2 authentication failed: '.($data['error'] ?? $data['error_description'] ?? "HTTP {$statusCode}"),
            );
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiresAt = microtime(true) + ($data['expires_in'] ?? 3600) - 60;
    }
}
