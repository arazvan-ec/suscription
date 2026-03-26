<?php

declare(strict_types=1);

namespace App\Domain;

enum ErrorType: string
{
    case Transient = 'transient';
    case Permanent = 'permanent';
}
