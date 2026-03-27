<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class SendNotificationCommand
{
    public function __construct(
        public array $recipients,
        public string $editorialId,
        public string $editorialTitle,
        public string $channel = 'email',
    ) {}
}
