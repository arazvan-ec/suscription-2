<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
