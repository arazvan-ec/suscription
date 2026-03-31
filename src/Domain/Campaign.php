<?php

declare(strict_types=1);

namespace App\Domain;

use Symfony\Component\Uid\Uuid;

final class Campaign
{
    private Uuid $id;
    private string $editorialId;
    private \DateTimeImmutable $scheduledAt;
    private CampaignStatus $status;
    private ?string $mailchimpCampaignId;
    private int $retryCount;
    private ?ErrorType $errorType;
    private ?string $errorMessage;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $editorialId,
        \DateTimeImmutable $scheduledAt,
    ) {
        $this->id = Uuid::v7();
        $this->editorialId = $editorialId;
        $this->scheduledAt = $scheduledAt;
        $this->status = CampaignStatus::Scheduled;
        $this->mailchimpCampaignId = null;
        $this->retryCount = 0;
        $this->errorType = null;
        $this->errorMessage = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEditorialId(): string
    {
        return $this->editorialId;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getStatus(): CampaignStatus
    {
        return $this->status;
    }

    public function getMailchimpCampaignId(): ?string
    {
        return $this->mailchimpCampaignId;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getErrorType(): ?ErrorType
    {
        return $this->errorType;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function markAsSending(): void
    {
        $this->status = CampaignStatus::Sending;
        $this->touch();
    }

    public function markAsDone(): void
    {
        $this->status = CampaignStatus::Done;
        $this->touch();
    }

    public function cancel(?string $reason = null): void
    {
        $this->status = CampaignStatus::Cancelled;
        $this->errorType = ErrorType::Permanent;
        $this->errorMessage = $reason;
        $this->touch();
    }

    public function fail(string $message, ErrorType $errorType): void
    {
        $this->status = CampaignStatus::Failed;
        $this->errorType = $errorType;
        $this->errorMessage = $message;
        $this->touch();
    }

    public function setMailchimpCampaignId(string $mailchimpCampaignId): void
    {
        $this->mailchimpCampaignId = $mailchimpCampaignId;
        $this->touch();
    }

    public function incrementRetry(): void
    {
        $this->retryCount++;
        $this->touch();
    }

    public function reschedule(): void
    {
        $this->status = CampaignStatus::Scheduled;
        $this->touch();
    }

    public function hasRetriesLeft(int $maxRetries = 3): bool
    {
        return $this->retryCount < $maxRetries;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
