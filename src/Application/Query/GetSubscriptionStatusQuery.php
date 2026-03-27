<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\ValueObject\EntityType;

final readonly class GetSubscriptionStatusQuery
{
    public function __construct(
        public string $userId,
        public EntityType $entityType,
        public string $entityId,
    ) {}
}
