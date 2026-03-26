<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Client;

use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use App\Infrastructure\Client\EditorialServiceClient;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class EditorialServiceClientTest extends TestCase
{
    public function testGetEditorialReturnsData(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(200, [], json_encode([
            'title' => 'Match report: Real Madrid 3-1 Barcelona',
            'url' => 'https://example.com/articles/match-report',
            'scheduled_at' => '2026-03-26T08:00:00+00:00',
            'journalist_id' => 'journalist-456',
            'is_published' => true,
        ])));

        $client = new EditorialServiceClient($mockClient, 'https://editorial.test');
        $editorial = $client->getEditorial('ed-123');

        $this->assertSame('Match report: Real Madrid 3-1 Barcelona', $editorial->title);
        $this->assertSame('https://example.com/articles/match-report', $editorial->url);
        $this->assertSame('journalist-456', $editorial->journalistId);
        $this->assertTrue($editorial->isPublished);
    }

    public function testGetEditorialThrowsTransientOnServerError(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(500));

        $client = new EditorialServiceClient($mockClient, 'https://editorial.test');

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('editorial-service returned 500');

        $client->getEditorial('ed-123');
    }

    public function testGetEditorialThrowsPermanentOnNotFound(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(404));

        $client = new EditorialServiceClient($mockClient, 'https://editorial.test');

        $this->expectException(PermanentErrorException::class);

        $client->getEditorial('ed-nonexistent');
    }

    public function testGetEditorialThrowsTransientOnNetworkError(): void
    {
        $mockClient = new MockClient();
        $mockClient->addException(new \Http\Client\Exception\NetworkException(
            'Connection timed out',
            new \Nyholm\Psr7\Request('GET', 'https://editorial.test/editorials/ed-123'),
        ));

        $client = new EditorialServiceClient($mockClient, 'https://editorial.test');

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('editorial-service unavailable');

        $client->getEditorial('ed-123');
    }
}
