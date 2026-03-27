<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\EntityType;

final readonly class SubscriptionCreatedEvent
{
    public function __construct(
        public string $userId,
        public string $email,
        public EntityType $entityType,
        public string $entityId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
