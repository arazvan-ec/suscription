<?php

declare(strict_types=1);

namespace App\Domain;

interface MailchimpClientInterface
{
    public function createCampaign(string $audienceId, string $subject, string $html): string;

    public function sendCampaign(string $campaignId): void;

    public function getCampaignStatus(string $campaignId): string;
}
