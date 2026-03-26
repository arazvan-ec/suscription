<?php

declare(strict_types=1);

namespace App\Infrastructure\Client;

use App\Domain\EditorialData;
use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use Http\Client\HttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;

final class EditorialServiceClient
{
    private readonly Psr17Factory $factory;

    public function __construct(
        private readonly HttpClient $editorialHttpClient,
        private readonly string $baseUrl,
    ) {
        $this->factory = new Psr17Factory();
    }

    public function getEditorial(string $editorialId): EditorialData
    {
        try {
            $request = $this->factory->createRequest('GET', $this->baseUrl . "/editorials/{$editorialId}");
            $response = $this->editorialHttpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 500) {
                throw new TransientErrorException("editorial-service returned {$statusCode}");
            }

            if ($statusCode >= 400) {
                throw new PermanentErrorException(
                    "editorial-service returned {$statusCode}",
                    'editorial_not_found',
                );
            }

            $data = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

            return new EditorialData(
                title: $data['title'],
                url: $data['url'],
                scheduledAt: new \DateTimeImmutable($data['scheduled_at']),
                journalistId: $data['journalist_id'],
                isPublished: $data['is_published'],
            );
        } catch (ClientExceptionInterface $e) {
            throw new TransientErrorException(
                'editorial-service unavailable: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
