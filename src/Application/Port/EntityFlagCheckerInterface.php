<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Event\EditorialPublishedEvent;

interface EntityFlagCheckerInterface
{
    /**
     * Returns entities that have the "follow" flag enabled.
     *
     * @return array<array{type: \App\Domain\ValueObject\EntityType, id: string}>
     */
    public function getEnabledEntities(EditorialPublishedEvent $event): array;
}
