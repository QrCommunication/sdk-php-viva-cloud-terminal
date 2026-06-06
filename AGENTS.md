# Viva Wallet Cloud Terminal SDK — Agent Instructions

See [CLAUDE.md](CLAUDE.md) for complete SDK architecture, patterns, and implementation guidelines.

## Quick Reference

- **Package**: `qrcommunication/viva-cloud-terminal-sdk`
- **Namespace**: `QrCommunication\VivaCloudTerminal\`
- **Entry point**: `VivaCloudTerminalClient`
- **Scope**: merchant Cloud Terminal API `/ecr/v1/` only (ISV `/ecr/isv/v1/` lives in `qrcommunication/viva-isv-sdk`)
- **Resources**: 3 (`devices`, `transactions`, `sessions`)
- **Auth**: single mode — OAuth2 `client_credentials` → Bearer token
- **Amounts**: always in cents (int); `currencyCode` is ISO 4217 numeric string (`'978'`)
- **Pattern**: Resource pattern (`$viva->transactions->sale()`)

## Public methods by resource

- `devices`: `search()`
- `transactions`: `sale()`, `capturePreauth()`, `refund()`, `unreferencedRefund()`, `fastRefund()`, `rebate()`, `createAction()`, `getAction()`
- `sessions`: `get()`, `listByDate()`, `abort()`, `pollUntilComplete()`
- client shortcut: `pollUntilComplete()`, `getConfig()`, `invalidateToken()`

## Conventions

- PHP 8.2+ `declare(strict_types=1)`, Resources `final`
- camelCase payloads, `array_filter` for null params, full PHPDoc per method
- Transaction methods return `['session_id' => ..., 'response' => ...]`; poll the session for the outcome
- Guzzle injectable via 2nd `HttpClient` ctor arg for tests
- `vendor/bin/phpunit` green; `vendor/bin/phpstan analyse src tests --level=6` clean

## Test card (demo)

`4111111111111111`, CVV `111`, 3DS `Secret!33`.
