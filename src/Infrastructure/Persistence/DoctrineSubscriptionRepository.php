<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Subscription;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\EntityType;
use App\Domain\ValueObject\SubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctrineSubscriptionRepository extends ServiceEntityRepository implements SubscriptionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $subscription): void
    {
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();
    }

    public function findByUserAndEntity(string $userId, EntityType $entityType, string $entityId): ?Subscription
    {
        return $this->findOneBy([
            'userId' => $userId,
            'entityType' => $entityType,
            'entityId' => $entityId,
        ]);
    }

    public function findActiveByUserId(string $userId): array
    {
        return $this->findBy([
            'userId' => $userId,
            'status' => SubscriptionStatus::ACTIVE,
        ]);
    }

    public function findActiveByEntities(array $entities): array
    {
        if (empty($entities)) {
            return [];
        }

        $qb = $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', SubscriptionStatus::ACTIVE);

        $orConditions = [];
        foreach ($entities as $i => $entity) {
            $orConditions[] = $qb->expr()->andX(
                $qb->expr()->eq('s.entityType', ":type_{$i}"),
                $qb->expr()->eq('s.entityId', ":id_{$i}"),
            );
            $qb->setParameter("type_{$i}", $entity['type']);
            $qb->setParameter("id_{$i}", $entity['id']);
        }

        $qb->andWhere($qb->expr()->orX(...$orConditions));

        return $qb->getQuery()->getResult();
    }

    public function hasActiveSubscriptionsForAny(array $entities): bool
    {
        if (empty($entities)) {
            return false;
        }

        $qb = $this->createQueryBuilder('s')
            ->select('1')
            ->where('s.status = :status')
            ->setParameter('status', SubscriptionStatus::ACTIVE)
            ->setMaxResults(1);

        $orConditions = [];
        foreach ($entities as $i => $entity) {
            $orConditions[] = $qb->expr()->andX(
                $qb->expr()->eq('s.entityType', ":type_{$i}"),
                $qb->expr()->eq('s.entityId', ":id_{$i}"),
            );
            $qb->setParameter("type_{$i}", $entity['type']);
            $qb->setParameter("id_{$i}", $entity['id']);
        }

        $qb->andWhere($qb->expr()->orX(...$orConditions));

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}
