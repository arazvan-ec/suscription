<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Client;

use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use App\Infrastructure\Client\MailchimpClient;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class MailchimpClientTest extends TestCase
{
    public function testCreateCampaignReturnsCampaignId(): void
    {
        $mockClient = new MockClient();
        // Response for POST /campaigns
        $mockClient->addResponse(new Response(200, [], json_encode(['id' => 'mc-campaign-123'])));
        // Response for PUT /campaigns/mc-campaign-123/content
        $mockClient->addResponse(new Response(200, [], '{}'));

        $client = new MailchimpClient($mockClient, 'test-api-key', 'us1');
        $campaignId = $client->createCampaign('audience-1', 'New article', '<h1>Hello</h1>');

        $this->assertSame('mc-campaign-123', $campaignId);
    }

    public function testSendCampaign(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(204));

        $client = new MailchimpClient($mockClient, 'test-api-key', 'us1');
        $client->sendCampaign('mc-campaign-123');

        $request = $mockClient->getLastRequest();
        $this->assertStringContainsString('/campaigns/mc-campaign-123/actions/send', $request->getUri()->getPath());
    }

    public function testGetCampaignStatus(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(200, [], json_encode(['status' => 'sent'])));

        $client = new MailchimpClient($mockClient, 'test-api-key', 'us1');
        $status = $client->getCampaignStatus('mc-campaign-123');

        $this->assertSame('sent', $status);
    }

    public function testThrowsTransientOn429WithRetryAfter(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(429, ['Retry-After' => '60']));

        $client = new MailchimpClient($mockClient, 'test-api-key', 'us1');

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('rate limited');

        $client->sendCampaign('mc-campaign-123');
    }

    public function testThrowsTransientOn500(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(500));

        $client = new MailchimpClient($mockClient, 'test-api-key', 'us1');

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('Mailchimp returned 500');

        $client->sendCampaign('mc-campaign-123');
    }

    public function testThrowsPermanentOn400(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(400, [], '{"detail":"Invalid audience"}'));

        $client = new MailchimpClient($mockClient, 'test-api-key', 'us1');

        $this->expectException(PermanentErrorException::class);

        $client->sendCampaign('mc-campaign-123');
    }

    public function testThrowsTransientOnNetworkError(): void
    {
        $mockClient = new MockClient();
        $mockClient->addException(new \Http\Client\Exception\NetworkException(
            'Connection timed out',
            new \Nyholm\Psr7\Request('POST', 'https://us1.api.mailchimp.com/3.0/campaigns'),
        ));

        $client = new MailchimpClient($mockClient, 'test-api-key', 'us1');

        $this->expectException(TransientErrorException::class);
        $this->expectExceptionMessage('Mailchimp unavailable');

        $client->sendCampaign('mc-campaign-123');
    }

    public function testAuthorizationHeaderIsSent(): void
    {
        $mockClient = new MockClient();
        $mockClient->addResponse(new Response(200, [], json_encode(['status' => 'sent'])));

        $client = new MailchimpClient($mockClient, 'my-secret-key', 'us1');
        $client->getCampaignStatus('mc-123');

        $request = $mockClient->getLastRequest();
        $this->assertSame('Bearer my-secret-key', $request->getHeaderLine('Authorization'));
    }
}
