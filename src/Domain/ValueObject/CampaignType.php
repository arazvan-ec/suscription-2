<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum CampaignType: string
{
    case EDITORIAL_NOTIFICATION = 'editorial_notification';
}
