<?php

declare(strict_types=1);

namespace App\Domain;

interface CampaignRepositoryInterface
{
    public function find(string $campaignId): ?Campaign;

    public function findScheduledByEditorialId(string $editorialId): ?Campaign;

    public function findByStatus(CampaignStatus $status): array;

    public function save(Campaign $campaign): void;
}
