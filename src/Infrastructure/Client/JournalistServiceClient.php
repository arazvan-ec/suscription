<?php

declare(strict_types=1);

namespace App\Infrastructure\Client;

use App\Domain\JournalistData;
use App\Domain\JournalistServiceInterface;
use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use Http\Client\HttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;

final class JournalistServiceClient implements JournalistServiceInterface
{
    private readonly Psr17Factory $factory;

    public function __construct(
        private readonly HttpClient $journalistHttpClient,
        private readonly string $baseUrl,
    ) {
        $this->factory = new Psr17Factory();
    }

    public function getJournalist(string $journalistId): JournalistData
    {
        try {
            $request = $this->factory->createRequest('GET', $this->baseUrl . "/journalists/{$journalistId}");
            $response = $this->journalistHttpClient->sendRequest($request);
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

            $data = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

            return new JournalistData(
                name: $data['name'],
                audienceId: $data['audience_id'] ?? null,
            );
        } catch (ClientExceptionInterface $e) {
            throw new TransientErrorException(
                'journalist-service unavailable: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
