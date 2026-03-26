<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum EntityType: string
{
    case JOURNALIST = 'journalist';
    case TAG = 'tag';
    case SECTION = 'section';
}
