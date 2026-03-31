<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\CampaignReconciler;
use App\Domain\Campaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\CampaignStatus;
use App\Domain\MailchimpClientInterface;
use App\Domain\TransientErrorException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CampaignReconcilerTest extends TestCase
{
    private CampaignRepositoryInterface $repository;
    private MailchimpClientInterface $mailchimpClient;
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CampaignRepositoryInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
    }

    private function reconciler(): CampaignReconciler
    {
        return new CampaignReconciler(
            $this->repository,
            $this->mailchimpClient,
            $this->bus,
            new NullLogger(),
        );
    }

    public function testReconcilesConfirmedSentCampaign(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $campaign->markAsSending();
        $campaign->setMailchimpCampaignId('mc-123');

        $this->repository->method('findByStatus')->willReturn([$campaign]);
        $this->mailchimpClient->method('getCampaignStatus')->willReturn('sent');
        $this->bus->expects($this->never())->method('dispatch');

        $count = $this->reconciler()->reconcile();

        $this->assertSame(1, $count);
        $this->assertSame(CampaignStatus::Done, $campaign->getStatus());
    }

    public function testReschedulesCampaignNotSentInMailchimp(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $campaign->markAsSending();
        $campaign->setMailchimpCampaignId('mc-123');

        $this->repository->method('findByStatus')->willReturn([$campaign]);
        $this->mailchimpClient->method('getCampaignStatus')->willReturn('schedule');
        $this->bus->expects($this->once())->method('dispatch')
            ->willReturnCallback(fn ($msg) => new Envelope($msg));

        $count = $this->reconciler()->reconcile();

        $this->assertSame(1, $count);
        $this->assertSame(CampaignStatus::Scheduled, $campaign->getStatus());
    }

    public function testReschedulesCampaignWithoutMailchimpId(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $campaign->markAsSending();

        $this->repository->method('findByStatus')->willReturn([$campaign]);
        $this->mailchimpClient->expects($this->never())->method('getCampaignStatus');
        $this->bus->expects($this->once())->method('dispatch')
            ->willReturnCallback(fn ($msg) => new Envelope($msg));

        $count = $this->reconciler()->reconcile();

        $this->assertSame(1, $count);
        $this->assertSame(CampaignStatus::Scheduled, $campaign->getStatus());
    }

    public function testReschedulesOnMailchimpError(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $campaign->markAsSending();
        $campaign->setMailchimpCampaignId('mc-123');

        $this->repository->method('findByStatus')->willReturn([$campaign]);
        $this->mailchimpClient->method('getCampaignStatus')
            ->willThrowException(new TransientErrorException('Mailchimp down'));
        $this->bus->expects($this->once())->method('dispatch')
            ->willReturnCallback(fn ($msg) => new Envelope($msg));

        $count = $this->reconciler()->reconcile();

        $this->assertSame(1, $count);
        $this->assertSame(CampaignStatus::Scheduled, $campaign->getStatus());
    }

    public function testReturnsZeroWhenNoCampaignsToReconcile(): void
    {
        $this->repository->method('findByStatus')->willReturn([]);

        $count = $this->reconciler()->reconcile();

        $this->assertSame(0, $count);
    }
}
