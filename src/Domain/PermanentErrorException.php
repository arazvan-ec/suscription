<?php

declare(strict_types=1);

namespace App\Domain;

final class PermanentErrorException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $reason = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
