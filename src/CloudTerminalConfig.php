<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal;

use QrCommunication\VivaCloudTerminal\Enums\Environment;

/**
 * Cloud Terminal configuration — holds the OAuth client credentials and the
 * target environment.
 *
 * The merchant Cloud Terminal API (/ecr/v1/) uses a single auth mode:
 * OAuth2 client_credentials → Bearer token. The token is fetched from
 * `accounts.vivapayments.com/connect/token` and used against
 * `api.vivapayments.com/ecr/v1/...` (demo hosts in sandbox).
 */
final class CloudTerminalConfig
{
    public readonly Environment $environment;

    public function __construct(
        // OAuth client credentials (Bearer token, client_credentials grant)
        public readonly string $clientId,
        public readonly string $clientSecret,
        // Environment
        string|Environment $environment = Environment::DEMO,
    ) {
        $this->environment = $environment instanceof Environment
            ? $environment
            : Environment::from($environment);
    }

    /**
     * OAuth token host (POST /connect/token only).
     */
    public function accountsUrl(): string
    {
        return $this->environment->accountsUrl();
    }

    /**
     * API host for all /ecr/v1/ calls.
     */
    public function apiUrl(): string
    {
        return $this->environment->apiUrl();
    }

    /**
     * Returns true when the client is configured for the production environment.
     */
    public function isProduction(): bool
    {
        return $this->environment === Environment::PRODUCTION;
    }

    /**
     * Returns true when the client is configured for the sandbox (demo) environment.
     */
    public function isSandbox(): bool
    {
        return $this->environment === Environment::DEMO;
    }
}
