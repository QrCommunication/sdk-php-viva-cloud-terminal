# Viva Wallet Cloud Terminal SDK (PHP)

PHP SDK for the **Viva Wallet Cloud Terminal API** — the merchant (`/ecr/v1/`)
variant of Viva's EFT POS solution. It lets your back-office (ECR / cash
register / web app) drive a physical card terminal over Viva's REST API:
initiate sales, capture pre-auths, refund, and poll session results.

> For the **ISV / Partner** variant (`/ecr/isv/v1/`, connected merchants,
> composite auth), use [`qrcommunication/viva-isv-sdk`](https://packagist.org/packages/qrcommunication/viva-isv-sdk) instead.

- **Package**: `qrcommunication/viva-cloud-terminal-sdk`
- **Namespace**: `QrCommunication\VivaCloudTerminal\`
- **Auth**: OAuth2 `client_credentials` → Bearer token
- **Amounts**: always in **cents** (int) — `1170` = 11.70 EUR
- **PHP**: 8.2+ · **Guzzle**: 7.8+

---

## Installation

```bash
composer require qrcommunication/viva-cloud-terminal-sdk
```

---

## Quickstart

```php
use QrCommunication\VivaCloudTerminal\VivaCloudTerminalClient;

$viva = new VivaCloudTerminalClient(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    environment: 'demo', // 'demo' (sandbox) or 'production'
);

// 1. (recommended) confirm the terminal is Live before transacting
$devices = $viva->devices->search(statusId: 1);

// 2. initiate a sale on the terminal
$sale = $viva->transactions->sale(
    terminalId: '16000010',
    amount: 1170,            // 11.70 EUR, in cents
    cashRegisterId: 'CR-01',
    merchantReference: 'order-42',
);

// 3. poll until the customer completes (or declines) on the device
$result = $viva->pollUntilComplete($sale['session_id']);

if (($result['success'] ?? false) === true) {
    // $result['transactionId'], $result['orderCode'], $result['amount'], ...
}
```

---

## Authentication

The Cloud Terminal API uses a **single** auth mode:

| Step | Host | Auth |
|------|------|------|
| Token | `accounts.vivapayments.com/connect/token` (demo: `demo-accounts...`) | Basic `client_id:client_secret`, `grant_type=client_credentials` |
| API calls | `api.vivapayments.com/ecr/v1/...` (demo: `demo-api...`) | `Authorization: Bearer {token}` |

The SDK fetches and caches the Bearer token automatically (refreshed 60s before
expiry). Call `$viva->invalidateToken()` to force a fresh handshake.

All `/ecr/v1/` payloads use **camelCase** keys.

---

## Resources & methods

### `$viva->devices`

| Method | Endpoint | Description |
|--------|----------|-------------|
| `search(?int $statusId, ?string $sourceCode)` | `POST /ecr/v1/devices:search` | Discover POS devices and their status |

### `$viva->transactions`

| Method | Endpoint | Description |
|--------|----------|-------------|
| `sale(...)` | `POST /ecr/v1/transactions:sale` | Initiate a card sale |
| `capturePreauth(...)` | `POST /ecr/v1/transactions:preauth-completion` | Capture a pre-authorized transaction |
| `refund(...)` | `POST /ecr/v1/transactions:refund` | Referenced refund of an original sale |
| `unreferencedRefund(...)` | `POST /ecr/v1/transactions:unreferenced-refund` | Standalone refund (no parent sale) |
| `fastRefund(...)` | `POST /ecr/v1/transactions:fast-refund` | Swift refund (Visa/MC/Maestro) |
| `rebate(...)` | `POST /ecr/v1/transactions:rebate` | Rebate to a card |
| `createAction(array $payload)` | `POST /ecr/v1/actions` | Create a device action (e.g. `aade-fim-control`) |
| `getAction(string $actionId)` | `GET /ecr/v1/actions/{actionId}` | Fetch an action result (202 while processing) |

### `$viva->sessions`

| Method | Endpoint | Description |
|--------|----------|-------------|
| `get(string $sessionId)` | `GET /ecr/v1/sessions/{sessionId}` | Retrieve one session |
| `listByDate(string $date, ?bool $aadeAutonomouslyOnly)` | `GET /ecr/v1/sessions?date=...` | List sessions for a date |
| `abort(string $sessionId, string $cashRegisterId)` | `DELETE /ecr/v1/sessions/{sessionId}` | Abort an active session |
| `pollUntilComplete(string $sessionId, int $timeoutSeconds = 120, int $intervalMs = 3000)` | repeated `GET` | Poll until terminal state |

`pollUntilComplete()` is also available as a shortcut on the client:
`$viva->pollUntilComplete($sessionId)`.

---

## Examples

### Sale + polling

```php
$sale = $viva->transactions->sale(
    terminalId: '16000010',
    amount: 2599,                 // 25.99 EUR
    cashRegisterId: 'CR-01',
    customerTrns: 'Table 12',
    showReceipt: true,
);

$final = $viva->pollUntilComplete($sale['session_id'], timeoutSeconds: 90);
```

### Pre-auth then capture

```php
// Pre-authorize (requires preauth enabled on your Viva account)
$preauth = $viva->transactions->sale(
    terminalId: '16000010',
    amount: 5000,
    cashRegisterId: 'CR-01',
    preauth: true,
);
$viva->pollUntilComplete($preauth['session_id']);

// Later, capture the final amount
$capture = $viva->transactions->capturePreauth(
    parentSessionId: $preauth['session_id'],
    terminalId: '16000010',
    amount: 4200,                 // capture less than authorized
    cashRegisterId: 'CR-01',
);
$viva->pollUntilComplete($capture['session_id']);
```

### Refund a sale

```php
$refund = $viva->transactions->refund(
    parentSessionId: $sale['session_id'],
    terminalId: '16000010',
    amount: 1170,
    cashRegisterId: 'CR-01',
);
$viva->pollUntilComplete($refund['session_id']);
```

### Abort a stuck session

```php
$viva->sessions->abort($sale['session_id'], cashRegisterId: 'CR-01');
```

### Advanced fields (fiscalisation, etc.)

Pass any additional API field via the `$extra` array (merged into the payload
as-is, camelCase):

```php
$viva->transactions->sale(
    terminalId: '16000010',
    amount: 1170,
    cashRegisterId: 'CR-01',
    extra: [
        'fiscalisationData' => [ /* ... */ ],
        'aadeProviderId' => '999',
    ],
);
```

---

## Test card (demo environment)

| Field | Value |
|-------|-------|
| Card number | `4111111111111111` |
| CVV | `111` |
| 3DS password | `Secret!33` |

Common decline test amounts: `9951` (insufficient funds), `9954` (expired card),
`9920` (stolen card), `9957` (not permitted), `9961` (withdrawal limit).

---

## Error handling

```php
use QrCommunication\VivaCloudTerminal\Exceptions\ApiException;
use QrCommunication\VivaCloudTerminal\Exceptions\AuthenticationException;
use QrCommunication\VivaCloudTerminal\Exceptions\VivaException;

try {
    $viva->transactions->sale(/* ... */);
} catch (AuthenticationException $e) {
    // OAuth handshake failed (bad client_id/secret)
} catch (ApiException $e) {
    // HTTP 4xx/5xx from the API
    $e->getHttpStatus();   // e.g. 409
    $e->getErrorText();    // human-readable error
    $e->getErrorCode();    // Viva error code, if any
} catch (VivaException $e) {
    // base class for all SDK exceptions
}
```

---

## Event IDs (`EcrEventId`)

The session poll response returns an `eventId`. Use the `EcrEventId` enum to
interpret it:

```php
use QrCommunication\VivaCloudTerminal\Enums\EcrEventId;

$event = EcrEventId::tryFrom($result['eventId']);
$event?->isSuccessful(); // true on success
$event?->shouldPoll();   // true while IN_PROGRESS (1100)
$event?->label();        // human-readable label
```

| eventId | Enum | Meaning |
|---------|------|---------|
| 0 | `SUCCESS` | Transaction successful |
| 1003 | `TERMINAL_TIMEOUT` | Terminal timed out |
| 1006 | `DECLINED` | Declined |
| 1016 | `ABORTED` | Aborted |
| 1020 | `INSUFFICIENT_FUNDS` | Insufficient funds |
| 1099 | `GENERIC_ERROR` | Generic error |
| 1100 | `IN_PROGRESS` | Still processing (keep polling) |
| 6000 | `BAD_PARAMS` | Bad parameters |

---

## Development

```bash
composer install
vendor/bin/phpunit          # run the test suite
vendor/bin/phpstan analyse src tests --level=6
```

---

## License

MIT © QrCommunication. See [LICENSE](LICENSE).
