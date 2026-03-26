<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Entity\Subscription;
use App\Domain\Event\SubscriptionCreatedEvent;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class SubscribeHandler
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(SubscribeCommand $command): void
    {
        $existing = $this->subscriptionRepository->findByUserAndEntity(
            $command->userId,
            $command->entityType,
            $command->entityId,
        );

        if ($existing !== null) {
            if (!$existing->isActive()) {
                $existing->reactivate();
                $this->subscriptionRepository->save($existing);
            }

            return;
        }

        $subscription = Subscription::create(
            $command->userId,
            $command->email,
            $command->entityType,
            $command->entityId,
        );

        $this->subscriptionRepository->save($subscription);

        $this->messageBus->dispatch(new SubscriptionCreatedEvent(
            userId: $command->userId,
            email: $command->email,
            entityType: $command->entityType,
            entityId: $command->entityId,
        ));
    }
}
