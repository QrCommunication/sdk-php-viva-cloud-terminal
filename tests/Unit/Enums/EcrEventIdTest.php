<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaCloudTerminal\Enums\EcrEventId;

final class EcrEventIdTest extends TestCase
{
    #[Test]
    public function in_progress_should_poll_and_is_not_terminal(): void
    {
        $this->assertTrue(EcrEventId::IN_PROGRESS->shouldPoll());
        $this->assertFalse(EcrEventId::IN_PROGRESS->isTerminal());
        $this->assertFalse(EcrEventId::IN_PROGRESS->isSuccessful());
    }

    #[Test]
    public function success_is_terminal_and_successful(): void
    {
        $this->assertTrue(EcrEventId::SUCCESS->isTerminal());
        $this->assertTrue(EcrEventId::SUCCESS->isSuccessful());
        $this->assertFalse(EcrEventId::SUCCESS->shouldPoll());
        $this->assertSame('Transaction successful', EcrEventId::SUCCESS->label());
    }

    #[Test]
    public function declined_is_terminal_but_not_successful(): void
    {
        $this->assertTrue(EcrEventId::DECLINED->isTerminal());
        $this->assertFalse(EcrEventId::DECLINED->isSuccessful());
        $this->assertSame('Transaction declined', EcrEventId::DECLINED->label());
    }
}
