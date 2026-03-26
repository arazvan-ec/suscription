<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class JournalistData
{
    public function __construct(
        public string $name,
        public ?string $audienceId,
    ) {
    }
}
