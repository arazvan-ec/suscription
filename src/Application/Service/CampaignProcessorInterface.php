<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Campaign;

interface CampaignProcessorInterface
{
    public function process(Campaign $campaign): void;
}
