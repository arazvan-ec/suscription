<?php

declare(strict_types=1);

namespace App\Domain;

enum CampaignStatus: string
{
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Done = 'done';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
