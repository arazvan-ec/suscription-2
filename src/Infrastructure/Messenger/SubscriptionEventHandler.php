<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Command\SyncMailchimpCommand;
use App\Domain\Event\SubscriptionCreatedEvent;
use App\Domain\Event\SubscriptionDeletedEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Listens to subscription domain events and dispatches Mailchimp sync commands.
 */
final readonly class SubscriptionEventHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    #[AsMessageHandler]
    public function onSubscriptionCreated(SubscriptionCreatedEvent $event): void
    {
        $this->messageBus->dispatch(new SyncMailchimpCommand(
            email: $event->email,
            action: SyncMailchimpCommand::ACTION_SUBSCRIBE,
            tags: [$event->entityType->value . ':' . $event->entityId],
        ));
    }

    #[AsMessageHandler]
    public function onSubscriptionDeleted(SubscriptionDeletedEvent $event): void
    {
        $this->messageBus->dispatch(new SyncMailchimpCommand(
            email: $event->email,
            action: SyncMailchimpCommand::ACTION_UNSUBSCRIBE,
        ));
    }
}
