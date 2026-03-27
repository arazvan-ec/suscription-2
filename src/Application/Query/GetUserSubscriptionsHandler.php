<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Entity\Subscription;
use App\Domain\Repository\SubscriptionRepositoryInterface;

final readonly class GetUserSubscriptionsHandler
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    /** @return array<array{entity_type: string, entity_id: string}> */
    public function __invoke(GetUserSubscriptionsQuery $query): array
    {
        $subscriptions = $this->subscriptionRepository->findActiveByUserId($query->userId);

        return array_map(
            fn (Subscription $s) => [
                'entity_type' => $s->getEntityType()->value,
                'entity_id' => $s->getEntityId(),
            ],
            $subscriptions,
        );
    }
}
