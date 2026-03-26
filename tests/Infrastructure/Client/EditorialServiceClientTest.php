<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Client;

use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use App\Infrastructure\Client\EditorialServiceClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EditorialServiceClientTest extends TestCase
{
    public function testGetEditorialReturnsData(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'title' => 'Match report: Real Madrid 3-1 Barcelona',
            'url' => 'https://example.com/articles/match-report',
            'scheduled_at' => '2026-03-26T08:00:00+00:00',
            'journalist_id' => 'journalist-456',
            'is_published' => true,
        ]));

        $client = new EditorialServiceClient(new MockHttpClient($mockResponse));
        $editorial = $client->getEditorial('ed-123');

        $this->assertSame('Match report: Real Madrid 3-1 Barcelona', $editorial->title);
        $this->assertSame('https://example.com/articles/match-report', $editorial->url);
        $this->assertSame('journalist-456', $editorial->journalistId);
        $this->assertTrue($editorial->isPublished);
    }

    public function testGetEditorialThrowsTransientOnServerError(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $client = new EditorialServiceClient(new MockHttpClient($mockResponse));

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('editorial-service returned 500');

        $client->getEditorial('ed-123');
    }

    public function testGetEditorialThrowsPermanentOnNotFound(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 404]);
        $client = new EditorialServiceClient(new MockHttpClient($mockResponse));

        $this->expectException(PermanentErrorException::class);

        $client->getEditorial('ed-nonexistent');
    }

    public function testGetEditorialThrowsTransientOnNetworkError(): void
    {
        $mockResponse = new MockResponse('', ['error' => 'Connection timed out']);
        $client = new EditorialServiceClient(new MockHttpClient($mockResponse));

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('editorial-service unavailable');

        $client->getEditorial('ed-123');
    }
}
