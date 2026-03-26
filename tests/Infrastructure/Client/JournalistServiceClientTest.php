<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Client;

use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use App\Infrastructure\Client\JournalistServiceClient;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class JournalistServiceClientTest extends TestCase
{
    public function testGetJournalistReturnsData(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(200, [], json_encode([
            'name' => 'Carlos Garcia',
            'audience_id' => 'mc-audience-789',
        ])));

        $client = new JournalistServiceClient($mockClient, 'https://journalist.test');
        $journalist = $client->getJournalist('journalist-456');

        $this->assertSame('Carlos Garcia', $journalist->name);
        $this->assertSame('mc-audience-789', $journalist->audienceId);
    }

    public function testGetJournalistWithNullAudience(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(200, [], json_encode([
            'name' => 'Miguel Nuevo',
        ])));

        $client = new JournalistServiceClient($mockClient, 'https://journalist.test');
        $journalist = $client->getJournalist('journalist-new');

        $this->assertSame('Miguel Nuevo', $journalist->name);
        $this->assertNull($journalist->audienceId);
    }

    public function testGetJournalistThrowsTransientOnServerError(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(503));

        $client = new JournalistServiceClient($mockClient, 'https://journalist.test');

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('journalist-service returned 503');

        $client->getJournalist('journalist-456');
    }

    public function testGetJournalistThrowsPermanentOnNotFound(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(404));

        $client = new JournalistServiceClient($mockClient, 'https://journalist.test');

        $this->expectException(PermanentErrorException::class);

        $client->getJournalist('journalist-nonexistent');
    }

    public function testGetJournalistThrowsTransientOnNetworkError(): void
    {
        $mockClient = new MockClient();
        $mockClient->addException(new \Http\Client\Exception\NetworkException(
            'Connection refused',
            new \Nyholm\Psr7\Request('GET', 'https://journalist.test/journalists/journalist-456'),
        ));

        $client = new JournalistServiceClient($mockClient, 'https://journalist.test');

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('journalist-service unavailable');

        $client->getJournalist('journalist-456');
    }
}
