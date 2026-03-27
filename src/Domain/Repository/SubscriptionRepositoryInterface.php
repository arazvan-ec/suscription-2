<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Subscription;
use App\Domain\ValueObject\EntityType;

interface SubscriptionRepositoryInterface
{
    public function save(Subscription $subscription): void;

    public function findByUserAndEntity(string $userId, EntityType $entityType, string $entityId): ?Subscription;

    /** @return Subscription[] */
    public function findActiveByUserId(string $userId): array;

    /**
     * @param array<array{type: EntityType, id: string}> $entities
     * @return Subscription[]
     */
    public function findActiveByEntities(array $entities): array;

    /** @param array<array{type: EntityType, id: string}> $entities */
    public function hasActiveSubscriptionsForAny(array $entities): bool;
}
