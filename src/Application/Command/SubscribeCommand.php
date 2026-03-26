<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\ValueObject\EntityType;

final readonly class SubscribeCommand
{
    public function __construct(
        public string $userId,
        public string $email,
        public EntityType $entityType,
        public string $entityId,
    ) {}
}
