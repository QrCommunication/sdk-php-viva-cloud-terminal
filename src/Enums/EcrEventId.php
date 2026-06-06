<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Enums;

/**
 * Cloud Terminal API event IDs.
 *
 * Returned in the session poll response as `eventId`. Used to determine whether
 * a terminal transaction is still in progress (keep polling) or has reached a
 * terminal state (success / decline / abort / error).
 */
enum EcrEventId: int
{
    case SUCCESS = 0;
    case TERMINAL_TIMEOUT = 1003;
    case DECLINED = 1006;
    case ABORTED = 1016;
    case INSUFFICIENT_FUNDS = 1020;
    case GENERIC_ERROR = 1099;
    case IN_PROGRESS = 1100;
    case BAD_PARAMS = 6000;

    /**
     * True when the transaction has reached a final state (no longer processing).
     */
    public function isTerminal(): bool
    {
        return $this !== self::IN_PROGRESS;
    }

    /**
     * True when the transaction completed successfully.
     */
    public function isSuccessful(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * True while the session is still being processed and must keep being polled.
     */
    public function shouldPoll(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Human-readable label for the event.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Transaction successful',
            self::TERMINAL_TIMEOUT => 'Terminal timed out',
            self::DECLINED => 'Transaction declined',
            self::ABORTED => 'Transaction aborted',
            self::INSUFFICIENT_FUNDS => 'Insufficient funds',
            self::GENERIC_ERROR => 'Generic error',
            self::IN_PROGRESS => 'In progress',
            self::BAD_PARAMS => 'Bad parameters',
        };
    }
}
