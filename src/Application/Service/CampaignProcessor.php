<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Campaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\EditorialServiceInterface;
use App\Domain\JournalistServiceInterface;
use App\Domain\MailchimpClientInterface;
use App\Domain\PermanentErrorException;
use App\Domain\EmailRendererInterface;
use Psr\Log\LoggerInterface;

final class CampaignProcessor
{
    public function __construct(
        private readonly EditorialServiceInterface $editorialService,
        private readonly JournalistServiceInterface $journalistService,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly EmailRendererInterface $emailRenderer,
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \App\Domain\TransientErrorException on retryable errors
     */
    public function process(Campaign $campaign): void
    {
        $editorial = $this->editorialService->getEditorial($campaign->getEditorialId());

        if (!$editorial->isPublished) {
            $campaign->cancel('editorial_not_published');
            $this->campaignRepository->save($campaign);
            $this->logger->info('Campaign cancelled: editorial not published', [
                'editorial_id' => $campaign->getEditorialId(),
                'campaign_id' => $campaign->getId()->toRfc4122(),
            ]);
            return;
        }

        $journalist = $this->journalistService->getJournalist($editorial->journalistId);

        if ($journalist->audienceId === null) {
            $campaign->cancel('audience_id_null');
            $this->campaignRepository->save($campaign);
            $this->logger->info('Campaign cancelled: journalist has no audience', [
                'editorial_id' => $campaign->getEditorialId(),
                'journalist_id' => $editorial->journalistId,
                'campaign_id' => $campaign->getId()->toRfc4122(),
            ]);
            return;
        }

        try {
            $html = $this->emailRenderer->render(
                $journalist->name,
                $editorial->title,
                $editorial->url,
            );
        } catch (PermanentErrorException $e) {
            $campaign->cancel('template_error: ' . $e->getMessage());
            $this->campaignRepository->save($campaign);
            $this->logger->info('Campaign cancelled: template rendering failed', [
                'editorial_id' => $campaign->getEditorialId(),
                'campaign_id' => $campaign->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if ($campaign->getMailchimpCampaignId() === null) {
            $subject = "{$journalist->name} ha publicado un nuevo articulo";
            $mailchimpCampaignId = $this->mailchimpClient->createCampaign(
                $journalist->audienceId,
                $subject,
                $html,
            );
            $campaign->setMailchimpCampaignId($mailchimpCampaignId);
            $this->campaignRepository->save($campaign);
        }

        $this->mailchimpClient->sendCampaign($campaign->getMailchimpCampaignId());

        $campaign->markAsDone();
        $this->campaignRepository->save($campaign);

        $this->logger->info('Campaign sent successfully', [
            'editorial_id' => $campaign->getEditorialId(),
            'campaign_id' => $campaign->getId()->toRfc4122(),
            'mailchimp_campaign_id' => $campaign->getMailchimpCampaignId(),
        ]);
    }
}
