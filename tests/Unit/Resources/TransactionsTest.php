<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaCloudTerminal\CloudTerminalConfig;
use QrCommunication\VivaCloudTerminal\Enums\Environment;
use QrCommunication\VivaCloudTerminal\Resources\Transactions;
use QrCommunication\VivaCloudTerminal\Tests\Fakes\GuzzleMockFactory;

final class TransactionsTest extends TestCase
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

    /**
     * @return array{0: Transactions, 1: \GuzzleHttp\Handler\MockHandler}
     */
    private function resource(): array
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);

        return [new Transactions($http), $mockHandler];
    }

    /**
     * @return array<string, mixed>
     */
    private function assertBearerPost(?RequestInterface $request, string $path): array
    {
        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());
        $this->assertStringStartsWith('Bearer ', $request->getHeaderLine('Authorization'));

        return json_decode((string) $request->getBody(), true);
    }

    #[Test]
    public function it_creates_an_action(): void
    {
        [$resource, $mockHandler] = $this->resource();
        $payload = [
            'terminalId' => '16000010',
            'cashRegisterId' => 'XDE384678UY',
            'actionType' => 'aade-fim-control',
            'request' => ['token' => 'ECR0210U/RCFB77000041'],
        ];

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, $payload + ['actionId' => 'action-uuid-123']));

        $result = $resource->createAction($payload);

        $this->assertSame('action-uuid-123', $result['actionId']);

        $body = $this->assertBearerPost($mockHandler->getLastRequest(), '/ecr/v1/actions');
        $this->assertSame('aade-fim-control', $body['actionType']);
        $this->assertSame('ECR0210U/RCFB77000041', $body['request']['token']);
    }

    #[Test]
    public function it_gets_an_action_via_bearer_get(): void
    {
        [$resource, $mockHandler] = $this->resource();

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            'actionId' => 'action-uuid-123',
            'successfullyProcessed' => true,
        ]));

        $result = $resource->getAction('action-uuid-123');

        $this->assertTrue($result['successfullyProcessed']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertSame('/ecr/v1/actions/action-uuid-123', $lastRequest->getUri()->getPath());
        $this->assertStringStartsWith('Bearer ', $lastRequest->getHeaderLine('Authorization'));
    }

    #[Test]
    public function it_initiates_a_sale(): void
    {
        [$resource, $mockHandler] = $this->resource();

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::rawResponse(200)); // sale returns empty body

        $result = $resource->sale(
            terminalId: '16000010',
            amount: 1170,
            cashRegisterId: 'XDE384678UY',
            merchantReference: 'order-42',
            tipAmount: 50,
        );

        $this->assertArrayHasKey('session_id', $result);
        $this->assertSame([], $result['response']);

        $body = $this->assertBearerPost($mockHandler->getLastRequest(), '/ecr/v1/transactions:sale');
        $this->assertSame('16000010', $body['terminalId']);
        $this->assertSame(1170, $body['amount']);
        $this->assertSame('978', $body['currencyCode']);
        $this->assertSame('XDE384678UY', $body['cashRegisterId']);
        $this->assertSame('order-42', $body['merchantReference']);
        $this->assertSame(50, $body['tipAmount']);
        $this->assertSame($result['session_id'], $body['sessionId']);
    }

    #[Test]
    public function it_captures_a_preauth(): void
    {
        [$resource, $mockHandler] = $this->resource();

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::rawResponse(200));

        $result = $resource->capturePreauth(
            parentSessionId: 'parent-session-uuid',
            terminalId: '16000010',
            amount: 1170,
            cashRegisterId: 'XDE384678UY',
        );

        $body = $this->assertBearerPost($mockHandler->getLastRequest(), '/ecr/v1/transactions:preauth-completion');
        $this->assertSame('parent-session-uuid', $body['parentSessionId']);
        $this->assertSame(1170, $body['amount']);
        $this->assertNotSame('parent-session-uuid', $body['sessionId']);
        $this->assertSame($result['session_id'], $body['sessionId']);
    }

    #[Test]
    public function it_refunds_a_referenced_transaction(): void
    {
        [$resource, $mockHandler] = $this->resource();

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::rawResponse(200));

        $result = $resource->refund(
            parentSessionId: 'parent-session-uuid',
            terminalId: '16000010',
            amount: 1170,
            cashRegisterId: 'XDE384678UY',
            merchantReference: 'refund-42',
        );

        $body = $this->assertBearerPost($mockHandler->getLastRequest(), '/ecr/v1/transactions:refund');
        $this->assertSame('parent-session-uuid', $body['parentSessionId']);
        $this->assertSame(1170, $body['amount']);
        $this->assertSame('refund-42', $body['merchantReference']);
        $this->assertNotSame('parent-session-uuid', $body['sessionId']);
        $this->assertSame($result['session_id'], $body['sessionId']);
    }

    #[Test]
    public function it_performs_an_unreferenced_refund(): void
    {
        [$resource, $mockHandler] = $this->resource();

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::rawResponse(200));

        $result = $resource->unreferencedRefund(
            terminalId: '16000010',
            amount: 1170,
            cashRegisterId: 'XDE384678UY',
        );

        $body = $this->assertBearerPost($mockHandler->getLastRequest(), '/ecr/v1/transactions:unreferenced-refund');
        $this->assertSame('16000010', $body['terminalId']);
        $this->assertSame(1170, $body['amount']);
        $this->assertArrayNotHasKey('parentSessionId', $body);
        $this->assertSame($result['session_id'], $body['sessionId']);
    }

    #[Test]
    public function it_performs_a_fast_refund(): void
    {
        [$resource, $mockHandler] = $this->resource();

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::rawResponse(200));

        $result = $resource->fastRefund(
            terminalId: '16000010',
            amount: 1170,
            cashRegisterId: 'XDE384678UY',
        );

        $body = $this->assertBearerPost($mockHandler->getLastRequest(), '/ecr/v1/transactions:fast-refund');
        $this->assertSame(1170, $body['amount']);
        $this->assertSame($result['session_id'], $body['sessionId']);
    }

    #[Test]
    public function it_performs_a_rebate(): void
    {
        [$resource, $mockHandler] = $this->resource();

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::rawResponse(200));

        $result = $resource->rebate(
            terminalId: '16000010',
            amount: 1170,
            cashRegisterId: 'XDE384678UY',
        );

        $body = $this->assertBearerPost($mockHandler->getLastRequest(), '/ecr/v1/transactions:rebate');
        $this->assertSame(1170, $body['amount']);
        $this->assertSame($result['session_id'], $body['sessionId']);
    }
}
