<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

final readonly class AuthenticatedUser
{
    public function __construct(
        public string $id,
        public string $email,
    ) {}
}
