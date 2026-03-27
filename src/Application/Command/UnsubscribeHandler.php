<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Event\SubscriptionDeletedEvent;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class UnsubscribeHandler
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(UnsubscribeCommand $command): void
    {
        $subscription = $this->subscriptionRepository->findByUserAndEntity(
            $command->userId,
            $command->entityType,
            $command->entityId,
        );

        if ($subscription === null || !$subscription->isActive()) {
            return;
        }

        $subscription->deactivate();
        $this->subscriptionRepository->save($subscription);

        $this->messageBus->dispatch(new SubscriptionDeletedEvent(
            userId: $command->userId,
            email: $subscription->getEmail(),
            entityType: $command->entityType,
            entityId: $command->entityId,
        ));
    }
}
