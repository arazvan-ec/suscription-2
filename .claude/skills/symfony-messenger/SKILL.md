---
name: symfony-messenger
description: RabbitMQ/Messenger patterns for async processing and event-driven architecture
triggers:
  - consumer
  - Messenger
  - event
  - RabbitMQ
  - async
  - queue
  - message
---

# Symfony Messenger Skill

## Purpose

Guide Claude to implement async messaging with Symfony Messenger and RabbitMQ,
following El Confidencial's event-driven microservice patterns.

## Transport Configuration

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: enbandeja
                        type: topic
                    queues:
                        enbandeja_subscriptions:
                            binding_keys:
                                - 'subscription.#'
                        enbandeja_editorial:
                            binding_keys:
                                - 'editorial.published'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    max_delay: 30000

            failed:
                dsn: 'doctrine://default?queue_name=failed'

        routing:
            'App\Domain\Event\SubscriptionCreatedEvent': async
            'App\Domain\Event\SubscriptionDeletedEvent': async
            'App\Application\Command\SyncMailchimpCommand': async
            'App\Application\Command\ProcessCampaignCommand': async
```

## Event Pattern

Domain events are dispatched after successful persistence:

```php
// Domain Event
final readonly class SubscriptionCreatedEvent
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $entityType,
        public string $entityId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}

// Dispatching in Use Case
final class SubscribeHandler implements MessageHandlerInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $repository,
        private readonly MessageBusInterface $eventBus,
    ) {}

    public function __invoke(SubscribeCommand $command): void
    {
        $subscription = Subscription::create(
            $command->userId,
            $command->entityType,
            $command->entityId,
        );

        $this->repository->save($subscription);

        $this->eventBus->dispatch(new SubscriptionCreatedEvent(
            userId: $command->userId,
            email: $command->email,
            entityType: $command->entityType,
            entityId: $command->entityId,
        ));
    }
}
```

## Consumer Pattern — Editorial Handler

Consuming events from external services (Jarvis/CMS):

```php
#[AsMessageHandler(fromTransport: 'async')]
final class EditorialPublishedHandler
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
        private readonly CampaignRepositoryInterface $campaignRepo,
        private readonly EntityFlagChecker $flagChecker,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(EditorialPublishedEvent $event): void
    {
        $this->logger->info('Processing editorial.published', [
            'editorial_id' => $event->editorialId,
        ]);

        // 1. Verify flags enabled (journalist-svc, tag-svc, section-svc)
        $entities = $this->flagChecker->getEnabledEntities($event);

        if (empty($entities)) {
            $this->logger->info('No enabled entities, discarding');
            return;
        }

        // 2. Check if any entity has followers
        $hasFollowers = $this->subscriptionRepo->hasActiveSubscriptionsForAny($entities);

        if (!$hasFollowers) {
            $this->logger->info('No followers for any entity, discarding');
            return;
        }

        // 3. Create campaign
        $campaign = Campaign::create(
            type: CampaignType::EDITORIAL_NOTIFICATION,
            editorialId: $event->editorialId,
            audienceCriteria: $entities,
            scheduledAt: new \DateTimeImmutable('+5 minutes'),
        );

        $this->campaignRepo->save($campaign);

        $this->logger->info('Campaign created', [
            'campaign_id' => $campaign->getId(),
            'scheduled_at' => $campaign->getScheduledAt()->format('c'),
        ]);
    }
}
```

## Mailchimp Sync Handler

Async sync with Mailchimp Marketing API (non-blocking):

```php
#[AsMessageHandler(fromTransport: 'async')]
final class SyncMailchimpHandler
{
    public function __invoke(SyncMailchimpCommand $command): void
    {
        match ($command->action) {
            'subscribe' => $this->mailchimp->addToAudience($command->email, $command->tags),
            'unsubscribe' => $this->mailchimp->removeFromAudience($command->email),
        };
    }
}
```

## Campaign Worker Pattern

Worker that processes campaigns when `scheduled_at <= NOW()`:

```php
#[AsMessageHandler]
final class ProcessCampaignHandler
{
    public function __invoke(ProcessCampaignCommand $command): void
    {
        $campaign = $this->campaignRepo->find($command->campaignId);

        // Resolve fresh subscriber data (only active)
        $subscribers = $this->subscriptionRepo->findActiveByEntities(
            $campaign->getAudienceCriteria()
        );

        // Dispatch to notifier-service via RabbitMQ
        $this->messageBus->dispatch(new SendNotificationCommand(
            recipients: $subscribers,
            content: $campaign->buildContent(),
            channel: 'email',
        ));

        $campaign->markAsSent();
        $this->campaignRepo->save($campaign);
    }
}
```

## Rules

- One handler per message class
- Use `#[AsMessageHandler]` attribute, not YAML config
- Always log entry/exit of handlers with context
- Failed messages go to `failed` transport automatically
- Retry with exponential backoff (1s, 2s, 4s) max 3 retries
- Use `fromTransport` to bind handlers to specific queues
- Domain events are immutable value objects
- Never do HTTP calls in the main request cycle — dispatch async
