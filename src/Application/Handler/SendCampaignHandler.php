<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Message\SendCampaign;
use App\Application\Service\CampaignProcessorInterface;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\CampaignStatus;
use App\Domain\ErrorType;
use App\Domain\PermanentErrorException;
use App\Domain\TransientErrorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class SendCampaignHandler
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly CampaignProcessorInterface $campaignProcessor,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly int $maxRetries = 3,
    ) {
    }

    public function __invoke(SendCampaign $message): void
    {
        $campaign = $this->campaignRepository->find($message->campaignId);

        if ($campaign === null) {
            $this->logger->warning('Campaign not found, ignoring', [
                'campaign_id' => $message->campaignId,
            ]);
            return;
        }

        if ($campaign->getStatus() !== CampaignStatus::Scheduled) {
            $this->logger->info('Campaign not in scheduled state, ignoring', [
                'campaign_id' => $message->campaignId,
                'status' => $campaign->getStatus()->value,
            ]);
            return;
        }

        $campaign->markAsSending();
        $this->campaignRepository->save($campaign);

        try {
            $this->campaignProcessor->process($campaign);
        } catch (TransientErrorException $e) {
            $campaign->incrementRetry();

            if ($campaign->hasRetriesLeft($this->maxRetries)) {
                $campaign->reschedule();
                $this->campaignRepository->save($campaign);

                $this->messageBus->dispatch(new SendCampaign($message->campaignId));

                $this->logger->warning('Campaign transient error, retrying', [
                    'campaign_id' => $message->campaignId,
                    'retry_count' => $campaign->getRetryCount(),
                    'error' => $e->getMessage(),
                ]);
            } else {
                $campaign->fail($e->getMessage(), ErrorType::Transient);
                $this->campaignRepository->save($campaign);

                $this->logger->critical('Campaign failed after max retries', [
                    'campaign_id' => $message->campaignId,
                    'editorial_id' => $campaign->getEditorialId(),
                    'retry_count' => $campaign->getRetryCount(),
                    'error_type' => ErrorType::Transient->value,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (PermanentErrorException $e) {
            // Already handled by CampaignProcessor (cancel + log)
            // This catch is defensive — should not normally reach here
            $this->logger->error('Unexpected permanent error in handler', [
                'campaign_id' => $message->campaignId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
