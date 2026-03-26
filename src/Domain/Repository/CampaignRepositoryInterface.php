<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Campaign;

interface CampaignRepositoryInterface
{
    public function save(Campaign $campaign): void;

    public function find(string $id): ?Campaign;

    /** @return Campaign[] */
    public function findReadyToProcess(\DateTimeImmutable $now = new \DateTimeImmutable()): array;
}
