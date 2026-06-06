# Viva Wallet Cloud Terminal SDK — AI Instructions

> Ce fichier est automatiquement detecte par Claude Code, Cursor, Copilot et Codex.

## SDK Overview

Package PHP `qrcommunication/viva-cloud-terminal-sdk` pour la **Cloud Terminal API
variante MARCHAND** de Viva Wallet (`/ecr/v1/`). Pilote un terminal de paiement
physique (EFT POS) depuis un ECR / caisse / back-office : vente, capture de
pre-autorisation, remboursements, abort, et polling de session.

⚠ **Perimetre** : UNIQUEMENT les endpoints marchand `/ecr/v1/`. Les endpoints
ISV `/ecr/isv/v1/` (marchands connectes, composite auth, `isvDetails`) sont
geres par un SDK distinct : `qrcommunication/viva-isv-sdk`. Ne JAMAIS melanger
les deux.

## Architecture

```
VivaCloudTerminalClient (point d'entree, readonly properties)
+-- devices       -> Devices       (POST /ecr/v1/devices:search)
+-- transactions  -> Transactions  (POST /ecr/v1/transactions:* + /ecr/v1/actions)
+-- sessions      -> Sessions      (GET/DELETE /ecr/v1/sessions...)
+-- pollUntilComplete($sessionId)  (passthrough vers Sessions::pollUntilComplete)
```

## Couverture des endpoints (v1.0.0)

| Resource | Methode | Endpoint |
|----------|---------|----------|
| `devices` | `search()` | `POST /ecr/v1/devices:search` |
| `transactions` | `sale()` | `POST /ecr/v1/transactions:sale` |
| `transactions` | `capturePreauth()` | `POST /ecr/v1/transactions:preauth-completion` |
| `transactions` | `refund()` | `POST /ecr/v1/transactions:refund` |
| `transactions` | `unreferencedRefund()` | `POST /ecr/v1/transactions:unreferenced-refund` |
| `transactions` | `fastRefund()` | `POST /ecr/v1/transactions:fast-refund` |
| `transactions` | `rebate()` | `POST /ecr/v1/transactions:rebate` |
| `transactions` | `createAction()` | `POST /ecr/v1/actions` |
| `transactions` | `getAction()` | `GET /ecr/v1/actions/{actionId}` |
| `sessions` | `get()` | `GET /ecr/v1/sessions/{sessionId}` |
| `sessions` | `listByDate()` | `GET /ecr/v1/sessions?date=` |
| `sessions` | `abort()` | `DELETE /ecr/v1/sessions/{sessionId}?cashRegisterId=` |
| `sessions` | `pollUntilComplete()` | polling repete de `get()` |

## Authentification (1 seul mode)

OAuth2 `client_credentials` → Bearer token.

| Host | Auth | Endpoints |
|------|------|-----------|
| `accounts.vivapayments.com` (demo: `demo-accounts...`) | Basic `client_id:client_secret` + `grant_type=client_credentials` | `/connect/token` uniquement |
| `api.vivapayments.com` (demo: `demo-api...`) | `Authorization: Bearer {token}` | `/ecr/v1/...` |

Le token est cache en memoire dans `HttpClient` et rafraichi 60s avant expiration.
`invalidateToken()` force un nouveau handshake.

**CRITIQUE** : tous les payloads `/ecr/v1/` sont en **camelCase**.

## Instanciation

```php
$viva = new VivaCloudTerminalClient(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    environment: 'demo', // 'demo' | 'production'
);
```

## Patterns d'implementation

### Vente + polling
```php
$sale = $viva->transactions->sale(
    terminalId: '16000010',
    amount: 1170,            // centimes
    cashRegisterId: 'CR-01',
);
$result = $viva->pollUntilComplete($sale['session_id']);
```

### Pre-auth puis capture
```php
$preauth = $viva->transactions->sale(terminalId: '16000010', amount: 5000, cashRegisterId: 'CR-01', preauth: true);
$viva->pollUntilComplete($preauth['session_id']);
$capture = $viva->transactions->capturePreauth(
    parentSessionId: $preauth['session_id'],
    terminalId: '16000010', amount: 4200, cashRegisterId: 'CR-01',
);
```

### Remboursement reference
```php
$viva->transactions->refund(
    parentSessionId: $sale['session_id'],
    terminalId: '16000010', amount: 1170, cashRegisterId: 'CR-01',
);
```

### Abort
```php
$viva->sessions->abort($sessionId, cashRegisterId: 'CR-01');
```

## Conventions de retour

- Les methodes de `transactions` retournent `['session_id' => string, 'response' => array]`.
  La plupart des endpoints `/ecr/v1/transactions:*` renvoient un **body vide** (HTTP 200) :
  le resultat reel s'obtient en pollant la session via `sessions->get()` /
  `pollUntilComplete()`. Le `session_id` retourne est l'id genere (ou fourni)
  pour cette transaction.
- `sessions->get()` / `listByDate()` retournent le body decode brut.

## Conventions de code

- PHP 8.2+ strict types (`declare(strict_types=1)` partout)
- Resources `final`
- Montants en **centimes** (int)
- `currencyCode` = code ISO 4217 numerique en **string** (`'978'` = EUR)
- `terminalId` accepte `string|int`, caste en string dans le payload (la doc le type string)
- Params optionnels strippes via `array_filter(..., fn ($v) => $v !== null)`
- PHPDoc complet par methode
- Champs API additionnels (fiscalisation, AADE, etc.) passes via `$extra` (merge tel quel)
- PSR-4 : `QrCommunication\VivaCloudTerminal\`
- Guzzle 7.8+ injectable via le 2e param de `HttpClient::__construct` (pour les tests)

## Enum EcrEventId

```php
EcrEventId::tryFrom($result['eventId'])?->shouldPoll();   // true si 1100 (IN_PROGRESS)
EcrEventId::SUCCESS->isSuccessful();                       // true
EcrEventId::DECLINED->label();                             // 'Transaction declined'
```

| eventId | Enum | Sens |
|---------|------|------|
| 0 | SUCCESS | Reussie |
| 1003 | TERMINAL_TIMEOUT | Timeout terminal |
| 1006 | DECLINED | Refusee |
| 1016 | ABORTED | Annulee |
| 1020 | INSUFFICIENT_FUNDS | Fonds insuffisants |
| 1099 | GENERIC_ERROR | Erreur generique |
| 1100 | IN_PROGRESS | En cours (poller) |
| 6000 | BAD_PARAMS | Parametres incorrects |

## Exceptions

```
VivaException (RuntimeException)
+-- ApiException             -> Erreur HTTP (4xx/5xx)
+-- AuthenticationException  -> OAuth2 invalide (401)
```

`ApiException` expose `getHttpStatus()`, `getErrorText()`, `getErrorCode()`.

## Pieges CRITIQUES

1. **Body vide sur succes** : `transactions:sale` (et les autres) renvoient 200 sans body.
   Ne pas conclure l'echec sur un body vide — c'est le succes du *dispatch* vers le
   terminal. Le resultat de paiement s'obtient via le polling de session.
2. **`getAction()` renvoie 202 (body vide → [])** tant que l'action n'est pas finie.
3. **Polling** : `eventId === 1100` = continuer ; tout autre eventId = etat terminal.
   `pollUntilComplete()` s'arrete aussi sur un eventId inconnu (fail-safe).
4. **camelCase obligatoire** sur tous les payloads `/ecr/v1/`.
5. **Ne pas confondre avec le SDK ISV** : pas de `isvDetails`, pas de `terminalMerchantId`,
   pas de composite auth ici. Le marchand opere SON propre compte via OAuth Bearer.
6. **Abort utilise DELETE** ici (variante marchand), contrairement au SDK ISV qui utilise GET.

## Carte de test (demo)

- Numero : `4111111111111111`, CVV : `111`, 3DS : `Secret!33`
- Montants de declin : 9951 (insufficient), 9954 (expired), 9920 (stolen), 9957 (not permitted), 9961 (withdrawal limit)

## Tests

- 1 test par methode publique. `GuzzleMockFactory::create($config)` injecte un MockHandler.
- Toujours append `tokenResponse()` AVANT la reponse de l'endpoint (handshake OAuth).
- Assertions : methode HTTP, path, body camelCase, header `Authorization: Bearer ...`.
- `vendor/bin/phpunit` doit etre VERT. `vendor/bin/phpstan analyse src tests --level=6` doit etre clean.
