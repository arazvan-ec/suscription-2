<?php

declare(strict_types=1);

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\Campaign;
use App\Domain\ValueObject\CampaignStatus;
use App\Domain\ValueObject\CampaignType;
use App\Domain\ValueObject\EntityType;
use PHPUnit\Framework\TestCase;

final class CampaignTest extends TestCase
{
    public function testCreateCampaign(): void
    {
        $entities = [
            ['type' => EntityType::JOURNALIST, 'id' => 'journalist-1'],
            ['type' => EntityType::TAG, 'id' => 'tag-1'],
        ];

        $campaign = Campaign::create(
            type: CampaignType::EDITORIAL_NOTIFICATION,
            editorialId: 'editorial-123',
            audienceCriteria: $entities,
            scheduledAt: new \DateTimeImmutable('+5 minutes'),
        );

        $this->assertNotNull($campaign->getId());
        $this->assertSame(CampaignType::EDITORIAL_NOTIFICATION, $campaign->getType());
        $this->assertSame(CampaignStatus::PENDING, $campaign->getStatus());
        $this->assertSame('editorial-123', $campaign->getEditorialId());
        $this->assertCount(2, $campaign->getAudienceCriteria());
        $this->assertTrue($campaign->isPending());
    }

    public function testCampaignStatusTransitions(): void
    {
        $campaign = Campaign::create(
            type: CampaignType::EDITORIAL_NOTIFICATION,
            editorialId: 'editorial-123',
            audienceCriteria: [],
            scheduledAt: new \DateTimeImmutable('+5 minutes'),
        );

        $this->assertTrue($campaign->isPending());

        $campaign->markAsProcessing();
        $this->assertSame(CampaignStatus::PROCESSING, $campaign->getStatus());
        $this->assertFalse($campaign->isPending());

        $campaign->markAsSent();
        $this->assertSame(CampaignStatus::SENT, $campaign->getStatus());
    }

    public function testCampaignFailedStatus(): void
    {
        $campaign = Campaign::create(
            type: CampaignType::EDITORIAL_NOTIFICATION,
            editorialId: 'editorial-123',
            audienceCriteria: [],
            scheduledAt: new \DateTimeImmutable('+5 minutes'),
        );

        $campaign->markAsProcessing();
        $campaign->markAsFailed();

        $this->assertSame(CampaignStatus::FAILED, $campaign->getStatus());
    }

    public function testIsReadyToProcess(): void
    {
        $futureCampaign = Campaign::create(
            type: CampaignType::EDITORIAL_NOTIFICATION,
            editorialId: 'editorial-1',
            audienceCriteria: [],
            scheduledAt: new \DateTimeImmutable('+1 hour'),
        );

        $this->assertFalse($futureCampaign->isReadyToProcess());

        $pastCampaign = Campaign::create(
            type: CampaignType::EDITORIAL_NOTIFICATION,
            editorialId: 'editorial-2',
            audienceCriteria: [],
            scheduledAt: new \DateTimeImmutable('-1 minute'),
        );

        $this->assertTrue($pastCampaign->isReadyToProcess());
    }
}
