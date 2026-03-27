<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Campaign;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Domain\ValueObject\CampaignStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
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

    public function existsByEditorialId(string $editorialId): bool
    {
        return $this->createQueryBuilder('c')
            ->select('1')
            ->where('c.editorialId = :editorialId')
            ->setParameter('editorialId', $editorialId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    public function findReadyToProcess(\DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT id FROM campaigns WHERE status = :status AND scheduled_at <= :now ORDER BY scheduled_at ASC FOR UPDATE SKIP LOCKED';

        $result = $conn->executeQuery($sql, [
            'status' => CampaignStatus::PENDING->value,
            'now' => $now->format('Y-m-d H:i:s'),
        ]);

        $ids = $result->fetchFirstColumn();

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
