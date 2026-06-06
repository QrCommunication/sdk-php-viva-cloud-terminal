<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaCloudTerminal\CloudTerminalConfig;
use QrCommunication\VivaCloudTerminal\Enums\Environment;
use QrCommunication\VivaCloudTerminal\Resources\Sessions;
use QrCommunication\VivaCloudTerminal\Tests\Fakes\GuzzleMockFactory;

final class SessionsTest extends TestCase
{
    private CloudTerminalConfig $config;

    protected function setUp(): void
    {
        $this->config = new CloudTerminalConfig(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            environment: Environment::DEMO,
        );
    }

    #[Test]
    public function it_gets_a_session_via_bearer_get(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new Sessions($http);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'sessionId' => 'session-uuid',
            'success' => true,
            'eventId' => 0,
            'transactionId' => 'txn-uuid',
        ]));

        $result = $resource->get('session-uuid');

        $this->assertTrue($result['success']);
        $this->assertSame('txn-uuid', $result['transactionId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertSame('/ecr/v1/sessions/session-uuid', $lastRequest->getUri()->getPath());
        $this->assertStringStartsWith('Bearer ', $lastRequest->getHeaderLine('Authorization'));
    }

    #[Test]
    public function it_lists_sessions_by_date_via_bearer_get(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new Sessions($http);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            ['sessionId' => 'session-uuid', 'success' => true],
        ]));

        $result = $resource->listByDate('2026-06-06', aadeAutonomouslyOnly: true);

        $this->assertSame('session-uuid', $result[0]['sessionId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertSame('/ecr/v1/sessions', $lastRequest->getUri()->getPath());
        $query = $lastRequest->getUri()->getQuery();
        $this->assertStringContainsString('date=2026-06-06', $query);
        $this->assertStringContainsString('AadeAutonomouslyOnly=true', $query);
    }

    #[Test]
    public function it_aborts_a_session_via_bearer_delete(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new Sessions($http);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::rawResponse(200));

        $resource->abort('session-uuid', 'CR-01');

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('DELETE', $lastRequest->getMethod());
        $this->assertSame('/ecr/v1/sessions/session-uuid', $lastRequest->getUri()->getPath());
        $this->assertStringContainsString('cashRegisterId=CR-01', $lastRequest->getUri()->getQuery());
        $this->assertStringStartsWith('Bearer ', $lastRequest->getHeaderLine('Authorization'));
    }

    #[Test]
    public function it_polls_until_complete_and_returns_terminal_state(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new Sessions($http);

        // OAuth token, then two polls: in-progress (1100) then success (0).
        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['eventId' => 1100, 'success' => false]));
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, ['eventId' => 0, 'success' => true]));

        $result = $resource->pollUntilComplete('session-uuid', timeoutSeconds: 5, intervalMs: 1);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['eventId']);
    }
}
