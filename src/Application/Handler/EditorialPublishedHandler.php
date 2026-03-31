<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Message\EditorialPublished;
use App\Application\Message\SendCampaign;
use App\Domain\Campaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\EditorialServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class EditorialPublishedHandler
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly EditorialServiceInterface $editorialServiceClient,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(EditorialPublished $message): void
    {
        $editorialId = $message->editorialId;

        $existing = $this->campaignRepository->findScheduledByEditorialId($editorialId);
        if ($existing !== null) {
            $this->logger->info('Campaign already scheduled, ignoring duplicate event', [
                'editorial_id' => $editorialId,
            ]);
            return;
        }

        $editorial = $this->editorialServiceClient->getEditorial($editorialId);

        $campaign = new Campaign($editorialId, $editorial->scheduledAt);
        $this->campaignRepository->save($campaign);

        $delayMs = $this->calculateDelayMs($editorial->scheduledAt);

        $stamps = $delayMs > 0 ? [new DelayStamp($delayMs)] : [];
        $this->messageBus->dispatch(
            new SendCampaign($campaign->getId()->toRfc4122()),
            $stamps,
        );

        $this->logger->info('Campaign created and SendCampaign dispatched', [
            'editorial_id' => $editorialId,
            'campaign_id' => $campaign->getId()->toRfc4122(),
            'scheduled_at' => $editorial->scheduledAt->format('c'),
            'delay_ms' => $delayMs,
        ]);
    }

    private function calculateDelayMs(\DateTimeImmutable $scheduledAt): int
    {
        $now = new \DateTimeImmutable();
        $diffMs = ($scheduledAt->getTimestamp() - $now->getTimestamp()) * 1000;

        return max(0, $diffMs);
    }
}
