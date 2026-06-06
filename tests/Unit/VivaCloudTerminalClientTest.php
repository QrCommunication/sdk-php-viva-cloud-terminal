<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaCloudTerminal\CloudTerminalConfig;
use QrCommunication\VivaCloudTerminal\Enums\Environment;
use QrCommunication\VivaCloudTerminal\Resources\Devices;
use QrCommunication\VivaCloudTerminal\Resources\Sessions;
use QrCommunication\VivaCloudTerminal\Resources\Transactions;
use QrCommunication\VivaCloudTerminal\VivaCloudTerminalClient;

final class VivaCloudTerminalClientTest extends TestCase
{
    #[Test]
    public function it_exposes_the_three_resources_and_config(): void
    {
        $viva = new VivaCloudTerminalClient(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            environment: 'demo',
        );

        $this->assertInstanceOf(Devices::class, $viva->devices);
        $this->assertInstanceOf(Transactions::class, $viva->transactions);
        $this->assertInstanceOf(Sessions::class, $viva->sessions);
        $this->assertInstanceOf(CloudTerminalConfig::class, $viva->getConfig());
    }

    #[Test]
    public function it_resolves_environment_from_string(): void
    {
        $viva = new VivaCloudTerminalClient('id', 'secret', 'production');

        $this->assertSame(Environment::PRODUCTION, $viva->getConfig()->environment);
        $this->assertTrue($viva->getConfig()->isProduction());
        $this->assertFalse($viva->getConfig()->isSandbox());
    }

    #[Test]
    public function it_invalidates_the_token_without_error(): void
    {
        $viva = new VivaCloudTerminalClient('id', 'secret');

        $viva->invalidateToken();

        $this->assertTrue($viva->getConfig()->isSandbox());
    }
}
