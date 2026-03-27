<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\ValueObject\EntityType;

final readonly class UnsubscribeCommand
{
    public function __construct(
        public string $userId,
        public EntityType $entityType,
        public string $entityId,
    ) {}
}
