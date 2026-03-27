<?php

declare(strict_types=1);

namespace App\Application\Port;

interface MailchimpClientInterface
{
    public function addToAudience(string $email, array $tags = []): void;

    public function removeFromAudience(string $email): void;
}
