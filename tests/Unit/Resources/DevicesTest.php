<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaCloudTerminal\CloudTerminalConfig;
use QrCommunication\VivaCloudTerminal\Enums\Environment;
use QrCommunication\VivaCloudTerminal\Resources\Devices;
use QrCommunication\VivaCloudTerminal\Tests\Fakes\GuzzleMockFactory;

final class DevicesTest extends TestCase
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
    public function it_searches_devices_via_bearer_post(): void
    {
        [$http, $mockHandler] = GuzzleMockFactory::create($this->config);
        $resource = new Devices($http);

        $mockHandler->append(GuzzleMockFactory::tokenResponse());
        $mockHandler->append(GuzzleMockFactory::jsonResponse(200, [
            ['terminalId' => '16000010', 'statusId' => 1, 'sourceCode' => '543679'],
        ]));

        $result = $resource->search(statusId: 1, sourceCode: '543679');

        $this->assertSame('16000010', $result[0]['terminalId']);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertInstanceOf(Request::class, $lastRequest);
        $this->assertSame('POST', $lastRequest->getMethod());
        $this->assertSame('/ecr/v1/devices:search', $lastRequest->getUri()->getPath());
        $this->assertStringStartsWith('Bearer ', $lastRequest->getHeaderLine('Authorization'));

        $body = json_decode((string) $lastRequest->getBody(), true);
        $this->assertSame(1, $body['statusId']);
        $this->assertSame('543679', $body['sourceCode']);
    }
}
