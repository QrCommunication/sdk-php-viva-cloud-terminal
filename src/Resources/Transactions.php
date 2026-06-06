<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Resources;

use QrCommunication\VivaCloudTerminal\HttpClient;

/**
 * Transactions resource — Cloud Terminal merchant transaction operations.
 *
 * All endpoints under /ecr/v1/transactions:* plus the device actions endpoints
 * /ecr/v1/actions[/{actionId}]. Bearer auth, camelCase payloads.
 *
 * Conventions:
 * - Amounts are always in cents (int): 1170 = 11.70 EUR.
 * - currencyCode is the ISO 4217 numeric code as a string: '978' = EUR.
 * - Every transaction carries a unique `sessionId` (UUID); if omitted it is
 *   auto-generated. The session is then polled (see {@see Sessions::get()}).
 * - Optional fields are stripped via array_filter so only provided values are sent.
 *
 * The methods return the raw decoded API body. Most transaction endpoints
 * return an empty body (HTTP 200) — the result is obtained by polling the
 * session afterwards, so each method also surfaces the `sessionId` it used.
 */
final class Transactions
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Create an action request to be invoked on a POS device.
     *
     * POST /ecr/v1/actions — e.g. an `aade-fim-control` action. The payload is
     * passed through as-is (camelCase) and must include `terminalId`,
     * `cashRegisterId`, `actionType` and `request`.
     *
     * @param  array<string, mixed>  $payload  Action request body
     * @return array{terminalId?: string, cashRegisterId?: string, actionType?: string, actionId?: string}
     */
    public function createAction(array $payload): array
    {
        return $this->http->post('/ecr/v1/actions', $payload);
    }

    /**
     * Fetch the result of a previously created action.
     *
     * GET /ecr/v1/actions/{actionId}. Returns HTTP 202 (empty body → []) while
     * the action is still processing, or the action result once completed.
     *
     * @return array<string, mixed>  Action data (successfullyProcessed, response, ...)
     */
    public function getAction(string $actionId): array
    {
        return $this->http->get('/ecr/v1/actions/'.rawurlencode($actionId));
    }

    /**
     * Initiate a sale transaction on a POS terminal.
     *
     * POST /ecr/v1/transactions:sale.
     *
     * @param  string|int  $terminalId  Target terminal ID (e.g. '16000010')
     * @param  int  $amount  Amount to authorize, in cents
     * @param  string  $cashRegisterId  Cash register identification (set by the merchant)
     * @param  string|null  $merchantReference  Free-text merchant reference (defaults to "SDK-{sessionId}")
     * @param  string  $currencyCode  ISO 4217 numeric code as string ('978' = EUR)
     * @param  int  $tipAmount  Desired tip amount in cents (not compatible with preauth)
     * @param  bool|null  $preauth  Pre-authorization flag (requires Viva enablement)
     * @param  string|null  $customerTrns  Free-text customer reference
     * @param  string|null  $paymentMethod  Default payment method displayed (e.g. 'CardPresent')
     * @param  bool|null  $skipSurcharge  Disable surcharge for this transaction
     * @param  bool|null  $showTransactionResult  Show the transaction result on the terminal
     * @param  bool|null  $showReceipt  Show the receipt + result on the terminal
     * @param  string|null  $sessionId  Session UUID (auto-generated when null)
     * @param  array<string, mixed>  $extra  Additional API fields passed through as-is (e.g. fiscalisationData)
     * @return array{session_id: string, response: array<string, mixed>}
     */
    public function sale(
        string|int $terminalId,
        int $amount,
        string $cashRegisterId,
        ?string $merchantReference = null,
        string $currencyCode = '978',
        int $tipAmount = 0,
        ?bool $preauth = null,
        ?string $customerTrns = null,
        ?string $paymentMethod = null,
        ?bool $skipSurcharge = null,
        ?bool $showTransactionResult = null,
        ?bool $showReceipt = null,
        ?string $sessionId = null,
        array $extra = [],
    ): array {
        $sessionId ??= $this->generateUuid();

        $payload = array_filter([
            'sessionId' => $sessionId,
            'terminalId' => (string) $terminalId,
            'cashRegisterId' => $cashRegisterId,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'merchantReference' => $merchantReference ?? 'SDK-'.$sessionId,
            'tipAmount' => $tipAmount,
            'preauth' => $preauth,
            'customerTrns' => $customerTrns,
            'paymentMethod' => $paymentMethod,
            'skipSurcharge' => $skipSurcharge,
            'showTransactionResult' => $showTransactionResult,
            'showReceipt' => $showReceipt,
        ], fn ($v): bool => $v !== null) + $extra;

        $response = $this->http->post('/ecr/v1/transactions:sale', $payload);

        return ['session_id' => $sessionId, 'response' => $response];
    }

    /**
     * Capture a previously authorized pre-auth transaction.
     *
     * POST /ecr/v1/transactions:preauth-completion. Captures the amount held by
     * the original pre-auth identified by `parentSessionId`.
     *
     * @param  string  $parentSessionId  Session UUID of the original pre-auth to capture
     * @param  string|int  $terminalId  Target terminal ID
     * @param  int  $amount  Amount to capture, in cents
     * @param  string  $cashRegisterId  Cash register identification
     * @param  string|null  $merchantReference  Free-text merchant reference
     * @param  string  $currencyCode  ISO 4217 numeric code as string ('978' = EUR)
     * @param  string|null  $customerTrns  Free-text customer reference
     * @param  bool|null  $showTransactionResult  Show the transaction result on the terminal
     * @param  bool|null  $showReceipt  Show the receipt + result on the terminal
     * @param  string|null  $sessionId  Session UUID for the capture (auto-generated when null)
     * @param  array<string, mixed>  $extra  Additional API fields passed through as-is
     * @return array{session_id: string, response: array<string, mixed>}
     */
    public function capturePreauth(
        string $parentSessionId,
        string|int $terminalId,
        int $amount,
        string $cashRegisterId,
        ?string $merchantReference = null,
        string $currencyCode = '978',
        ?string $customerTrns = null,
        ?bool $showTransactionResult = null,
        ?bool $showReceipt = null,
        ?string $sessionId = null,
        array $extra = [],
    ): array {
        $sessionId ??= $this->generateUuid();

        $payload = array_filter([
            'sessionId' => $sessionId,
            'parentSessionId' => $parentSessionId,
            'terminalId' => (string) $terminalId,
            'cashRegisterId' => $cashRegisterId,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'merchantReference' => $merchantReference ?? 'SDK-capture-'.$sessionId,
            'customerTrns' => $customerTrns,
            'showTransactionResult' => $showTransactionResult,
            'showReceipt' => $showReceipt,
        ], fn ($v): bool => $v !== null) + $extra;

        $response = $this->http->post('/ecr/v1/transactions:preauth-completion', $payload);

        return ['session_id' => $sessionId, 'response' => $response];
    }

    /**
     * Refund (cancel) a previous sale, referencing the original session.
     *
     * POST /ecr/v1/transactions:refund. A fresh `sessionId` is generated for the
     * refund operation itself; `parentSessionId` references the original sale.
     *
     * @param  string  $parentSessionId  Session UUID of the original sale to refund
     * @param  string|int  $terminalId  Target terminal ID
     * @param  int  $amount  Amount to refund, in cents
     * @param  string  $cashRegisterId  Cash register identification
     * @param  string|null  $merchantReference  Free-text merchant reference
     * @param  string  $currencyCode  ISO 4217 numeric code as string ('978' = EUR)
     * @param  string|null  $customerTrns  Free-text customer reference
     * @param  bool|null  $showTransactionResult  Show the transaction result on the terminal
     * @param  bool|null  $showReceipt  Show the receipt + result on the terminal
     * @param  string|null  $sessionId  Session UUID for the refund (auto-generated when null)
     * @param  array<string, mixed>  $extra  Additional API fields passed through as-is
     * @return array{session_id: string, response: array<string, mixed>}
     */
    public function refund(
        string $parentSessionId,
        string|int $terminalId,
        int $amount,
        string $cashRegisterId,
        ?string $merchantReference = null,
        string $currencyCode = '978',
        ?string $customerTrns = null,
        ?bool $showTransactionResult = null,
        ?bool $showReceipt = null,
        ?string $sessionId = null,
        array $extra = [],
    ): array {
        $sessionId ??= $this->generateUuid();

        $payload = array_filter([
            'sessionId' => $sessionId,
            'parentSessionId' => $parentSessionId,
            'terminalId' => (string) $terminalId,
            'cashRegisterId' => $cashRegisterId,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'merchantReference' => $merchantReference ?? 'SDK-refund-'.$sessionId,
            'customerTrns' => $customerTrns,
            'showTransactionResult' => $showTransactionResult,
            'showReceipt' => $showReceipt,
        ], fn ($v): bool => $v !== null) + $extra;

        $response = $this->http->post('/ecr/v1/transactions:refund', $payload);

        return ['session_id' => $sessionId, 'response' => $response];
    }

    /**
     * Initiate an unreferenced (standalone) refund — not tied to an original sale.
     *
     * POST /ecr/v1/transactions:unreferenced-refund.
     *
     * @param  string|int  $terminalId  Target terminal ID
     * @param  int  $amount  Amount to refund, in cents
     * @param  string  $cashRegisterId  Cash register identification
     * @param  string|null  $merchantReference  Free-text merchant reference
     * @param  string  $currencyCode  ISO 4217 numeric code as string ('978' = EUR)
     * @param  string|null  $customerTrns  Free-text customer reference
     * @param  bool|null  $showTransactionResult  Show the transaction result on the terminal
     * @param  bool|null  $showReceipt  Show the receipt + result on the terminal
     * @param  string|null  $sessionId  Session UUID (auto-generated when null)
     * @param  array<string, mixed>  $extra  Additional API fields passed through as-is (e.g. fiscalisationData)
     * @return array{session_id: string, response: array<string, mixed>}
     */
    public function unreferencedRefund(
        string|int $terminalId,
        int $amount,
        string $cashRegisterId,
        ?string $merchantReference = null,
        string $currencyCode = '978',
        ?string $customerTrns = null,
        ?bool $showTransactionResult = null,
        ?bool $showReceipt = null,
        ?string $sessionId = null,
        array $extra = [],
    ): array {
        $sessionId ??= $this->generateUuid();

        $payload = array_filter([
            'sessionId' => $sessionId,
            'terminalId' => (string) $terminalId,
            'cashRegisterId' => $cashRegisterId,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'merchantReference' => $merchantReference ?? 'SDK-unref-refund-'.$sessionId,
            'customerTrns' => $customerTrns,
            'showTransactionResult' => $showTransactionResult,
            'showReceipt' => $showReceipt,
        ], fn ($v): bool => $v !== null) + $extra;

        $response = $this->http->post('/ecr/v1/transactions:unreferenced-refund', $payload);

        return ['session_id' => $sessionId, 'response' => $response];
    }

    /**
     * Process a fast refund (swift refund on Visa/Mastercard/Maestro).
     *
     * POST /ecr/v1/transactions:fast-refund.
     *
     * @param  string|int  $terminalId  Target terminal ID
     * @param  int  $amount  Amount to refund, in cents
     * @param  string  $cashRegisterId  Cash register identification
     * @param  string|null  $merchantReference  Free-text merchant reference
     * @param  string  $currencyCode  ISO 4217 numeric code as string ('978' = EUR)
     * @param  string|null  $customerTrns  Free-text customer reference
     * @param  bool|null  $showTransactionResult  Show the transaction result on the terminal
     * @param  bool|null  $showReceipt  Show the receipt + result on the terminal
     * @param  string|null  $sessionId  Session UUID (auto-generated when null)
     * @param  array<string, mixed>  $extra  Additional API fields passed through as-is
     * @return array{session_id: string, response: array<string, mixed>}
     */
    public function fastRefund(
        string|int $terminalId,
        int $amount,
        string $cashRegisterId,
        ?string $merchantReference = null,
        string $currencyCode = '978',
        ?string $customerTrns = null,
        ?bool $showTransactionResult = null,
        ?bool $showReceipt = null,
        ?string $sessionId = null,
        array $extra = [],
    ): array {
        $sessionId ??= $this->generateUuid();

        $payload = array_filter([
            'sessionId' => $sessionId,
            'terminalId' => (string) $terminalId,
            'cashRegisterId' => $cashRegisterId,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'merchantReference' => $merchantReference ?? 'SDK-fast-refund-'.$sessionId,
            'customerTrns' => $customerTrns,
            'showTransactionResult' => $showTransactionResult,
            'showReceipt' => $showReceipt,
        ], fn ($v): bool => $v !== null) + $extra;

        $response = $this->http->post('/ecr/v1/transactions:fast-refund', $payload);

        return ['session_id' => $sessionId, 'response' => $response];
    }

    /**
     * Process a rebate to a Visa/Mastercard/Maestro card.
     *
     * POST /ecr/v1/transactions:rebate.
     *
     * @param  string|int  $terminalId  Target terminal ID
     * @param  int  $amount  Amount to rebate, in cents
     * @param  string  $cashRegisterId  Cash register identification
     * @param  string|null  $merchantReference  Free-text merchant reference
     * @param  string  $currencyCode  ISO 4217 numeric code as string ('978' = EUR)
     * @param  string|null  $customerTrns  Free-text customer reference
     * @param  bool|null  $showTransactionResult  Show the transaction result on the terminal
     * @param  bool|null  $showReceipt  Show the receipt + result on the terminal
     * @param  string|null  $sessionId  Session UUID (auto-generated when null)
     * @param  array<string, mixed>  $extra  Additional API fields passed through as-is
     * @return array{session_id: string, response: array<string, mixed>}
     */
    public function rebate(
        string|int $terminalId,
        int $amount,
        string $cashRegisterId,
        ?string $merchantReference = null,
        string $currencyCode = '978',
        ?string $customerTrns = null,
        ?bool $showTransactionResult = null,
        ?bool $showReceipt = null,
        ?string $sessionId = null,
        array $extra = [],
    ): array {
        $sessionId ??= $this->generateUuid();

        $payload = array_filter([
            'sessionId' => $sessionId,
            'terminalId' => (string) $terminalId,
            'cashRegisterId' => $cashRegisterId,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'merchantReference' => $merchantReference ?? 'SDK-rebate-'.$sessionId,
            'customerTrns' => $customerTrns,
            'showTransactionResult' => $showTransactionResult,
            'showReceipt' => $showReceipt,
        ], fn ($v): bool => $v !== null) + $extra;

        $response = $this->http->post('/ecr/v1/transactions:rebate', $payload);

        return ['session_id' => $sessionId, 'response' => $response];
    }

    /**
     * Generate an RFC 4122 v4 UUID for a transaction session.
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        );
    }
}
