<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Client;

use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use App\Infrastructure\Client\JournalistServiceClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class JournalistServiceClientTest extends TestCase
{
    public function testGetJournalistReturnsData(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'name' => 'Carlos Garcia',
            'audience_id' => 'mc-audience-789',
        ]));

        $client = new JournalistServiceClient(new MockHttpClient($mockResponse));
        $journalist = $client->getJournalist('journalist-456');

        $this->assertSame('Carlos Garcia', $journalist->name);
        $this->assertSame('mc-audience-789', $journalist->audienceId);
    }

    public function testGetJournalistWithNullAudience(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'name' => 'Miguel Nuevo',
        ]));

        $client = new JournalistServiceClient(new MockHttpClient($mockResponse));
        $journalist = $client->getJournalist('journalist-new');

        $this->assertSame('Miguel Nuevo', $journalist->name);
        $this->assertNull($journalist->audienceId);
    }

    public function testGetJournalistThrowsTransientOnServerError(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 503]);
        $client = new JournalistServiceClient(new MockHttpClient($mockResponse));

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('journalist-service returned 503');

        $client->getJournalist('journalist-456');
    }

    public function testGetJournalistThrowsPermanentOnNotFound(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 404]);
        $client = new JournalistServiceClient(new MockHttpClient($mockResponse));

        $this->expectException(PermanentErrorException::class);

        $client->getJournalist('journalist-nonexistent');
    }

    public function testGetJournalistThrowsTransientOnNetworkError(): void
    {
        $mockResponse = new MockResponse('', ['error' => 'Connection refused']);
        $client = new JournalistServiceClient(new MockHttpClient($mockResponse));

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('journalist-service unavailable');

        $client->getJournalist('journalist-456');
    }
}
