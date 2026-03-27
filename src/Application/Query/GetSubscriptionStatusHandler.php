<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Repository\SubscriptionRepositoryInterface;

final readonly class GetSubscriptionStatusHandler
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function __invoke(GetSubscriptionStatusQuery $query): bool
    {
        $subscription = $this->subscriptionRepository->findByUserAndEntity(
            $query->userId,
            $query->entityType,
            $query->entityId,
        );

        return $subscription !== null && $subscription->isActive();
    }
}
