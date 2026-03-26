<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class EditorialData
{
    public function __construct(
        public string $title,
        public string $url,
        public \DateTimeImmutable $scheduledAt,
        public string $journalistId,
        public bool $isPublished,
    ) {
    }
}
