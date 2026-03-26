<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Message\SendCampaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\CampaignStatus;
use App\Domain\MailchimpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class CampaignReconciler
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function reconcile(): int
    {
        $campaigns = $this->campaignRepository->findByStatus(CampaignStatus::Sending);
        $reconciled = 0;

        foreach ($campaigns as $campaign) {
            $mailchimpId = $campaign->getMailchimpCampaignId();

            if ($mailchimpId === null) {
                $campaign->reschedule();
                $this->campaignRepository->save($campaign);
                $this->messageBus->dispatch(new SendCampaign($campaign->getId()->toRfc4122()));

                $this->logger->info('Reconciled campaign without Mailchimp ID: rescheduled', [
                    'campaign_id' => $campaign->getId()->toRfc4122(),
                    'editorial_id' => $campaign->getEditorialId(),
                ]);
                $reconciled++;
                continue;
            }

            try {
                $status = $this->mailchimpClient->getCampaignStatus($mailchimpId);

                if ($status === 'sent') {
                    $campaign->markAsDone();
                    $this->campaignRepository->save($campaign);

                    $this->logger->info('Reconciled campaign: confirmed sent by Mailchimp', [
                        'campaign_id' => $campaign->getId()->toRfc4122(),
                        'mailchimp_campaign_id' => $mailchimpId,
                    ]);
                } else {
                    $campaign->reschedule();
                    $this->campaignRepository->save($campaign);
                    $this->messageBus->dispatch(new SendCampaign($campaign->getId()->toRfc4122()));

                    $this->logger->info('Reconciled campaign: not sent, rescheduled', [
                        'campaign_id' => $campaign->getId()->toRfc4122(),
                        'mailchimp_status' => $status,
                    ]);
                }
            } catch (\Throwable $e) {
                $campaign->reschedule();
                $this->campaignRepository->save($campaign);
                $this->messageBus->dispatch(new SendCampaign($campaign->getId()->toRfc4122()));

                $this->logger->warning('Reconciliation failed for campaign, rescheduled', [
                    'campaign_id' => $campaign->getId()->toRfc4122(),
                    'error' => $e->getMessage(),
                ]);
            }

            $reconciled++;
        }

        return $reconciled;
    }
}
