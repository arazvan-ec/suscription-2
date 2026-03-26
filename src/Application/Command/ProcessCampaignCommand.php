<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class ProcessCampaignCommand
{
    public function __construct(
        public string $campaignId,
    ) {}
}
