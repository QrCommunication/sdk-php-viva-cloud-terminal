# GitHub Copilot Instructions

See [CLAUDE.md](../CLAUDE.md) and [AGENTS.md](../AGENTS.md) for full architecture and patterns.

- **Package**: `qrcommunication/viva-cloud-terminal-sdk` (`QrCommunication\VivaCloudTerminal\`)
- **Scope**: merchant Cloud Terminal API `/ecr/v1/` only.
- **Auth**: OAuth2 `client_credentials` → Bearer token (single mode).
- **Resources**: `devices`, `transactions`, `sessions`.
- **Amounts** in cents (int); `currencyCode` as ISO 4217 numeric string (`'978'`).
- camelCase payloads; `array_filter` for null params; Resources `final`; strict types.
- Transaction methods return `['session_id' => ..., 'response' => ...]`; poll the session for the result.
- Test card (demo): `4111111111111111`, CVV `111`, 3DS `Secret!33`.
