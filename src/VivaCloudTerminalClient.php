<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal;

use QrCommunication\VivaCloudTerminal\Enums\Environment;
use QrCommunication\VivaCloudTerminal\Resources\Devices;
use QrCommunication\VivaCloudTerminal\Resources\Sessions;
use QrCommunication\VivaCloudTerminal\Resources\Transactions;

/**
 * Viva Wallet Cloud Terminal SDK — main entry point (merchant /ecr/v1/).
 *
 * Single auth mode: OAuth2 client_credentials → Bearer token, fetched from
 * accounts.vivapayments.com and used against api.vivapayments.com/ecr/v1/...
 * (demo hosts in sandbox).
 *
 * Usage:
 *
 *     $viva = new VivaCloudTerminalClient(
 *         clientId: 'your-client-id',
 *         clientSecret: 'your-client-secret',
 *         environment: 'demo',
 *     );
 *
 *     // 1. (optional) confirm the terminal is Live
 *     $devices = $viva->devices->search(statusId: 1);
 *
 *     // 2. initiate a sale
 *     $sale = $viva->transactions->sale(
 *         terminalId: '16000010',
 *         amount: 1170,            // 11.70 EUR
 *         cashRegisterId: 'CR-01',
 *     );
 *
 *     // 3. poll until the customer completes the payment on the device
 *     $result = $viva->pollUntilComplete($sale['session_id']);
 */
final class VivaCloudTerminalClient
{
    public readonly Devices $devices;

    public readonly Transactions $transactions;

    public readonly Sessions $sessions;

    private readonly CloudTerminalConfig $config;

    private readonly HttpClient $http;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string|Environment $environment = Environment::DEMO,
    ) {
        $this->config = new CloudTerminalConfig(
            clientId: $clientId,
            clientSecret: $clientSecret,
            environment: $environment,
        );

        $this->http = new HttpClient($this->config);

        $this->devices = new Devices($this->http);
        $this->transactions = new Transactions($this->http);
        $this->sessions = new Sessions($this->http);
    }

    public function getConfig(): CloudTerminalConfig
    {
        return $this->config;
    }

    /**
     * Drop the cached OAuth token, forcing a fresh handshake on the next call.
     */
    public function invalidateToken(): void
    {
        $this->http->invalidateToken();
    }

    /**
     * Convenience passthrough to {@see Sessions::pollUntilComplete()}.
     *
     * @return array<string, mixed>  Final session state
     */
    public function pollUntilComplete(string $sessionId, int $timeoutSeconds = 120, int $intervalMs = 3000): array
    {
        return $this->sessions->pollUntilComplete($sessionId, $timeoutSeconds, $intervalMs);
    }
}
