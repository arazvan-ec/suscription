<?php

declare(strict_types=1);

namespace App\Domain;

interface JournalistServiceInterface
{
    public function getJournalist(string $journalistId): JournalistData;
}
