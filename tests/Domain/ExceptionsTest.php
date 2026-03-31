<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use PHPUnit\Framework\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testTransientErrorIsRuntimeException(): void
    {
        $exception = new TransientErrorException('Mailchimp returned 500');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Mailchimp returned 500', $exception->getMessage());
    }

    public function testTransientErrorPreservesPrevious(): void
    {
        $previous = new \Exception('connection timeout');
        $exception = new TransientErrorException('editorial-service unavailable', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testPermanentErrorIsRuntimeException(): void
    {
        $exception = new PermanentErrorException('No audience', 'audience_id_null');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('No audience', $exception->getMessage());
        $this->assertSame('audience_id_null', $exception->getReason());
    }

    public function testPermanentErrorDefaultReason(): void
    {
        $exception = new PermanentErrorException('Template error');

        $this->assertSame('', $exception->getReason());
    }
}
