<?php

declare(strict_types=1);

namespace App\Infrastructure\Client;

use App\Domain\JournalistData;
use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class JournalistServiceClient
{
    public function __construct(
        private readonly HttpClientInterface $journalistServiceClient,
    ) {
    }

    public function getJournalist(string $journalistId): JournalistData
    {
        try {
            $response = $this->journalistServiceClient->request('GET', "/journalists/{$journalistId}");
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 500) {
                throw new TransientErrorException("journalist-service returned {$statusCode}");
            }

            if ($statusCode >= 400) {
                throw new PermanentErrorException(
                    "journalist-service returned {$statusCode}",
                    'journalist_not_found',
                );
            }

            $data = $response->toArray();

            return new JournalistData(
                name: $data['name'],
                audienceId: $data['audience_id'] ?? null,
            );
        } catch (TransportExceptionInterface $e) {
            throw new TransientErrorException(
                'journalist-service unavailable: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
