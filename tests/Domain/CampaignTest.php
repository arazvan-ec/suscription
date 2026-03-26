<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Campaign;
use App\Domain\CampaignStatus;
use App\Domain\ErrorType;
use PHPUnit\Framework\TestCase;

final class CampaignTest extends TestCase
{
    public function testNewCampaignIsScheduled(): void
    {
        $campaign = new Campaign('ed-123', new \DateTimeImmutable('2026-03-26 08:00:00'));

        $this->assertSame('ed-123', $campaign->getEditorialId());
        $this->assertSame(CampaignStatus::Scheduled, $campaign->getStatus());
        $this->assertSame(0, $campaign->getRetryCount());
        $this->assertNull($campaign->getMailchimpCampaignId());
        $this->assertNull($campaign->getErrorType());
        $this->assertNull($campaign->getErrorMessage());
    }

    public function testMarkAsSending(): void
    {
        $campaign = new Campaign('ed-123', new \DateTimeImmutable());
        $campaign->markAsSending();

        $this->assertSame(CampaignStatus::Sending, $campaign->getStatus());
    }

    public function testMarkAsDone(): void
    {
        $campaign = new Campaign('ed-123', new \DateTimeImmutable());
        $campaign->markAsSending();
        $campaign->markAsDone();

        $this->assertSame(CampaignStatus::Done, $campaign->getStatus());
    }

    public function testCancel(): void
    {
        $campaign = new Campaign('ed-123', new \DateTimeImmutable());
        $campaign->cancel('editorial_not_published');

        $this->assertSame(CampaignStatus::Cancelled, $campaign->getStatus());
        $this->assertSame(ErrorType::Permanent, $campaign->getErrorType());
        $this->assertSame('editorial_not_published', $campaign->getErrorMessage());
    }

    public function testFail(): void
    {
        $campaign = new Campaign('ed-123', new \DateTimeImmutable());
        $campaign->fail('Mailchimp 500', ErrorType::Transient);

        $this->assertSame(CampaignStatus::Failed, $campaign->getStatus());
        $this->assertSame(ErrorType::Transient, $campaign->getErrorType());
        $this->assertSame('Mailchimp 500', $campaign->getErrorMessage());
    }

    public function testIncrementRetry(): void
    {
        $campaign = new Campaign('ed-123', new \DateTimeImmutable());

        $this->assertTrue($campaign->hasRetriesLeft());
        $campaign->incrementRetry();
        $campaign->incrementRetry();
        $this->assertTrue($campaign->hasRetriesLeft());
        $campaign->incrementRetry();
        $this->assertFalse($campaign->hasRetriesLeft());
    }

    public function testReschedule(): void
    {
        $campaign = new Campaign('ed-123', new \DateTimeImmutable());
        $campaign->markAsSending();
        $campaign->reschedule();

        $this->assertSame(CampaignStatus::Scheduled, $campaign->getStatus());
    }

    public function testSetMailchimpCampaignId(): void
    {
        $campaign = new Campaign('ed-123', new \DateTimeImmutable());
        $campaign->setMailchimpCampaignId('mc_abc123');

        $this->assertSame('mc_abc123', $campaign->getMailchimpCampaignId());
    }
}
