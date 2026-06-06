<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Resources;

use QrCommunication\VivaCloudTerminal\Enums\EcrEventId;
use QrCommunication\VivaCloudTerminal\HttpClient;

/**
 * Sessions resource — retrieve, list and abort Cloud Terminal sessions.
 *
 * A session represents a single transaction lifecycle on a POS device. After
 * initiating a transaction (see {@see Transactions}), poll the session by id to
 * observe its outcome (`eventId`, `success`, `transactionId`, ...).
 *
 * Endpoints (Bearer auth):
 * - GET    /ecr/v1/sessions/{sessionId}
 * - GET    /ecr/v1/sessions?date=Y-m-d
 * - DELETE /ecr/v1/sessions/{sessionId}?cashRegisterId=...
 */
final class Sessions
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Retrieve a session by its id.
     *
     * GET /ecr/v1/sessions/{sessionId}.
     *
     * @return array<string, mixed>  Session data (success, eventId, transactionId, amount, ...)
     */
    public function get(string $sessionId): array
    {
        return $this->http->get('/ecr/v1/sessions/'.rawurlencode($sessionId));
    }

    /**
     * List all sessions carried out on a date.
     *
     * GET /ecr/v1/sessions?date=Y-m-d.
     *
     * @param  string  $date  Date in Y-m-d format
     * @param  bool|null  $aadeAutonomouslyOnly  Filter to AADE-autonomous sessions only (Greece)
     * @return array<int|string, mixed>  List of sessions
     */
    public function listByDate(string $date, ?bool $aadeAutonomouslyOnly = null): array
    {
        return $this->http->get('/ecr/v1/sessions', array_filter([
            'date' => $date,
            'AadeAutonomouslyOnly' => $aadeAutonomouslyOnly === null ? null : ($aadeAutonomouslyOnly ? 'true' : 'false'),
        ], fn ($v): bool => $v !== null));
    }

    /**
     * Abort an active session.
     *
     * DELETE /ecr/v1/sessions/{sessionId}?cashRegisterId=...
     * Only the cash register that created the transaction can abort it.
     *
     * @param  string  $sessionId  Session UUID to abort
     * @param  string  $cashRegisterId  Cash register identification (required)
     * @return array<string, mixed>
     */
    public function abort(string $sessionId, string $cashRegisterId): array
    {
        return $this->http->delete(
            '/ecr/v1/sessions/'.rawurlencode($sessionId),
            ['cashRegisterId' => $cashRegisterId],
        );
    }

    /**
     * Poll a session until it reaches a terminal state or the timeout elapses.
     *
     * Keeps polling while `eventId` is {@see EcrEventId::IN_PROGRESS} (1100),
     * sleeping `$intervalMs` between attempts. Returns the final session state.
     *
     * @param  string  $sessionId  Session UUID to poll
     * @param  int  $timeoutSeconds  Maximum total wait time
     * @param  int  $intervalMs  Delay between poll attempts, in milliseconds
     * @return array<string, mixed>  Final session state
     */
    public function pollUntilComplete(string $sessionId, int $timeoutSeconds = 120, int $intervalMs = 3000): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $session = [];

        while (microtime(true) < $deadline) {
            $session = $this->get($sessionId);

            $event = EcrEventId::tryFrom($session['eventId'] ?? -1);

            // Unknown event id or a terminal event → stop polling and return.
            if ($event === null || $event->isTerminal()) {
                return $session;
            }

            usleep($intervalMs * 1000);
        }

        if ($session === []) {
            return ['success' => false, 'eventId' => EcrEventId::TERMINAL_TIMEOUT->value, 'message' => 'SDK poll timeout'];
        }

        return $session;
    }
}
