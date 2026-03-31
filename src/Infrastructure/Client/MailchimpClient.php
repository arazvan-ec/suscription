<?php

declare(strict_types=1);

namespace App\Infrastructure\Client;

use App\Domain\MailchimpClientInterface;
use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use Http\Client\HttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;

final class MailchimpClient implements MailchimpClientInterface
{
    private readonly Psr17Factory $factory;
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClient $mailchimpHttpClient,
        private readonly string $apiKey,
        string $serverPrefix,
    ) {
        $this->factory = new Psr17Factory();
        $this->baseUrl = "https://{$serverPrefix}.api.mailchimp.com/3.0";
    }

    public function createCampaign(string $audienceId, string $subject, string $html): string
    {
        $campaignId = $this->doCreateCampaign($audienceId, $subject);
        $this->setContent($campaignId, $html);

        return $campaignId;
    }

    public function sendCampaign(string $campaignId): void
    {
        $request = $this->factory->createRequest('POST', "{$this->baseUrl}/campaigns/{$campaignId}/actions/send")
            ->withHeader('Authorization', "Bearer {$this->apiKey}")
            ->withHeader('Content-Type', 'application/json');

        $this->executeRequest($request, 'send campaign');
    }

    public function getCampaignStatus(string $campaignId): string
    {
        $request = $this->factory->createRequest('GET', "{$this->baseUrl}/campaigns/{$campaignId}")
            ->withHeader('Authorization', "Bearer {$this->apiKey}");

        $response = $this->executeRequest($request, 'get campaign status');
        $data = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

        return $data['status'];
    }

    private function doCreateCampaign(string $audienceId, string $subject): string
    {
        $body = json_encode([
            'type' => 'regular',
            'recipients' => ['list_id' => $audienceId],
            'settings' => ['subject_line' => $subject, 'from_name' => 'enBandeja', 'reply_to' => 'noreply@example.com'],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->factory->createRequest('POST', "{$this->baseUrl}/campaigns")
            ->withHeader('Authorization', "Bearer {$this->apiKey}")
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));

        $response = $this->executeRequest($request, 'create campaign');
        $data = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

        return $data['id'];
    }

    private function setContent(string $campaignId, string $html): void
    {
        $body = json_encode(['html' => $html], \JSON_THROW_ON_ERROR);

        $request = $this->factory->createRequest('PUT', "{$this->baseUrl}/campaigns/{$campaignId}/content")
            ->withHeader('Authorization', "Bearer {$this->apiKey}")
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));

        $this->executeRequest($request, 'set campaign content');
    }

    private function executeRequest(\Psr\Http\Message\RequestInterface $request, string $operation): \Psr\Http\Message\ResponseInterface
    {
        try {
            $response = $this->mailchimpHttpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                $retryAfter = $response->getHeaderLine('Retry-After');
                throw new TransientErrorException(
                    "Mailchimp rate limited on {$operation}. Retry-After: {$retryAfter}",
                );
            }

            if ($statusCode >= 500) {
                throw new TransientErrorException("Mailchimp returned {$statusCode} on {$operation}");
            }

            if ($statusCode >= 400) {
                $body = $response->getBody()->getContents();
                throw new PermanentErrorException(
                    "Mailchimp returned {$statusCode} on {$operation}: {$body}",
                    'mailchimp_client_error',
                );
            }

            return $response;
        } catch (ClientExceptionInterface $e) {
            throw new TransientErrorException(
                "Mailchimp unavailable on {$operation}: " . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
