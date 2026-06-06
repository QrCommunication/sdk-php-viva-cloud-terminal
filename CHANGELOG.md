# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-06-06

### Added — Initial release

PHP SDK for the Viva Wallet **Cloud Terminal API** (merchant `/ecr/v1/` variant).

- **`VivaCloudTerminalClient`** entry point with readonly resource properties
  (`$devices`, `$transactions`, `$sessions`), plus `getConfig()`,
  `invalidateToken()` and a `pollUntilComplete()` shortcut.
- **Auth**: OAuth2 `client_credentials` → Bearer token, cached in-memory and
  refreshed automatically (handled by `HttpClient`, Guzzle injectable for tests).
- **`Devices`**: `search()` — `POST /ecr/v1/devices:search`.
- **`Transactions`**:
  - `sale()` — `POST /ecr/v1/transactions:sale`
  - `capturePreauth()` — `POST /ecr/v1/transactions:preauth-completion`
  - `refund()` — `POST /ecr/v1/transactions:refund`
  - `unreferencedRefund()` — `POST /ecr/v1/transactions:unreferenced-refund`
  - `fastRefund()` — `POST /ecr/v1/transactions:fast-refund`
  - `rebate()` — `POST /ecr/v1/transactions:rebate`
  - `createAction()` — `POST /ecr/v1/actions`
  - `getAction()` — `GET /ecr/v1/actions/{actionId}`
- **`Sessions`**: `get()`, `listByDate()`, `abort()` (`DELETE`), and
  `pollUntilComplete()` (driven by the `EcrEventId` enum).
- **`EcrEventId`** enum (`isTerminal()`, `isSuccessful()`, `shouldPoll()`, `label()`).
- **`Environment`** enum (`demo` / `production`) and **`CloudTerminalConfig`**.
- **Exceptions**: `VivaException` → `ApiException`, `AuthenticationException`.
- Full unit test suite (one test per public method) using a Guzzle MockHandler.
- Documentation: `README.md`, `CLAUDE.md`, `AGENTS.md`, `openapi.yaml`.
