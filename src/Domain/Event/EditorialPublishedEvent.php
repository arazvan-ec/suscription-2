<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class EditorialPublishedEvent
{
    public function __construct(
        public string $editorialId,
        public string $title,
        public ?string $journalistId = null,
        public array $tagIds = [],
        public ?string $sectionId = null,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
