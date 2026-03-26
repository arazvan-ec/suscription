<?php

declare(strict_types=1);

namespace App\Domain;

interface CampaignRepositoryInterface
{
    public function findScheduledByEditorialId(string $editorialId): ?Campaign;

    public function save(Campaign $campaign): void;
}
