<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Resources;

use QrCommunication\VivaCloudTerminal\HttpClient;

/**
 * Devices resource — discover POS (EFT POS) devices and their status.
 *
 * Recommended as a pre-flight step before initiating any transaction,
 * especially over WAN connections, to confirm the terminal is `Live` (statusId 1).
 *
 * Endpoint: POST /ecr/v1/devices:search (Bearer auth, camelCase).
 */
final class Devices
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Search POS devices, optionally filtering by status and/or source code.
     *
     * statusId values:
     *   0 = WareHouse, 1 = Live, 2 = Ready To Ship, 3 = In Stock,
     *   4 = Pending Key Injection, 5 = Lost, 6 = Broken, ...
     *
     * Each device entry contains: terminalId (string), statusId (int),
     * sourceCode (string), virtualTerminalId (string).
     *
     * @param  int|null  $statusId  Filter by device status
     * @param  string|null  $sourceCode  Custom merchant-assigned device code
     * @return array<int|string, mixed>  List of devices
     */
    public function search(?int $statusId = null, ?string $sourceCode = null): array
    {
        return $this->http->post('/ecr/v1/devices:search', array_filter([
            'statusId' => $statusId,
            'sourceCode' => $sourceCode,
        ], fn ($v): bool => $v !== null));
    }
}
