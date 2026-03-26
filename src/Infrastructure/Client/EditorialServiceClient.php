<?php

declare(strict_types=1);

namespace App\Infrastructure\Client;

use App\Domain\EditorialData;
use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class EditorialServiceClient
{
    public function __construct(
        private readonly HttpClientInterface $editorialServiceClient,
    ) {
    }

    public function getEditorial(string $editorialId): EditorialData
    {
        try {
            $response = $this->editorialServiceClient->request('GET', "/editorials/{$editorialId}");
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

            $data = $response->toArray();

            return new EditorialData(
                title: $data['title'],
                url: $data['url'],
                scheduledAt: new \DateTimeImmutable($data['scheduled_at']),
                journalistId: $data['journalist_id'],
                isPublished: $data['is_published'],
            );
        } catch (TransportExceptionInterface $e) {
            throw new TransientErrorException(
                'editorial-service unavailable: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
