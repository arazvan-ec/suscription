<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Campaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\CampaignStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCampaignRepository implements CampaignRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findScheduledByEditorialId(string $editorialId): ?Campaign
    {
        return $this->em->createQueryBuilder()
            ->select('c')
            ->from(Campaign::class, 'c')
            ->where('c.editorialId = :editorialId')
            ->andWhere('c.status = :status')
            ->setParameter('editorialId', $editorialId)
            ->setParameter('status', CampaignStatus::Scheduled->value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function find(string $campaignId): ?Campaign
    {
        return $this->em->find(Campaign::class, $campaignId);
    }

    public function save(Campaign $campaign): void
    {
        $this->em->persist($campaign);
        $this->em->flush();
    }
}
