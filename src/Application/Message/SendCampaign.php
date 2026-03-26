<?php

declare(strict_types=1);

namespace App\Application\Message;

use Symfony\Component\Uid\Uuid;

final readonly class SendCampaign
{
    public function __construct(
        public string $campaignId,
    ) {
    }
}
