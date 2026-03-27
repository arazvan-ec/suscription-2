<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class SyncMailchimpCommand
{
    public const string ACTION_SUBSCRIBE = 'subscribe';
    public const string ACTION_UNSUBSCRIBE = 'unsubscribe';
    public const string ACTION_REMOVE_TAGS = 'remove_tags';

    public function __construct(
        public string $email,
        public string $action,
        public array $tags = [],
    ) {}
}
