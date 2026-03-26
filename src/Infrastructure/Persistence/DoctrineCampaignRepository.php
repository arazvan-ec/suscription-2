<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Campaign;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Domain\ValueObject\CampaignStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctrineCampaignRepository extends ServiceEntityRepository implements CampaignRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Campaign::class);
    }

    public function save(Campaign $campaign): void
    {
        $this->getEntityManager()->persist($campaign);
        $this->getEntityManager()->flush();
    }

    public function find(mixed $id, mixed $lockMode = null, mixed $lockVersion = null): ?Campaign
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    public function findReadyToProcess(\DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.scheduledAt <= :now')
            ->setParameter('status', CampaignStatus::PENDING)
            ->setParameter('now', $now)
            ->orderBy('c.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
