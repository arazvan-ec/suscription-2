<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Application\Port\EntityFlagCheckerInterface;
use App\Domain\Entity\Campaign;
use App\Domain\Event\EditorialPublishedEvent;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\EntityType;
use App\Infrastructure\Messenger\EditorialPublishedHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class EditorialPublishedHandlerTest extends TestCase
{
    public function testCreatesCampaignWhenEntitiesHaveFollowers(): void
    {
        $flagChecker = $this->createMock(EntityFlagCheckerInterface::class);
        $subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $campaignRepo = $this->createMock(CampaignRepositoryInterface::class);

        $entities = [
            ['type' => EntityType::JOURNALIST, 'id' => 'journalist-1'],
            ['type' => EntityType::TAG, 'id' => 'tag-1'],
        ];

        $flagChecker->expects($this->once())
            ->method('getEnabledEntities')
            ->willReturn($entities);

        $subscriptionRepo->expects($this->once())
            ->method('hasActiveSubscriptionsForAny')
            ->willReturn(true);

        $campaignRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Campaign::class));

        $handler = new EditorialPublishedHandler(
            $flagChecker,
            $subscriptionRepo,
            $campaignRepo,
            new NullLogger(),
        );

        $handler(new EditorialPublishedEvent(
            editorialId: 'editorial-1',
            title: 'Test Article',
            journalistId: 'journalist-1',
            tagIds: ['tag-1', 'tag-2'],
            sectionId: 'section-1',
        ));
    }

    public function testDiscardsWhenNoFollowers(): void
    {
        $flagChecker = $this->createMock(EntityFlagCheckerInterface::class);
        $subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $campaignRepo = $this->createMock(CampaignRepositoryInterface::class);

        $flagChecker->expects($this->once())
            ->method('getEnabledEntities')
            ->willReturn([
                ['type' => EntityType::JOURNALIST, 'id' => 'journalist-1'],
            ]);

        $subscriptionRepo->expects($this->once())
            ->method('hasActiveSubscriptionsForAny')
            ->willReturn(false);

        $campaignRepo->expects($this->never())->method('save');

        $handler = new EditorialPublishedHandler(
            $flagChecker,
            $subscriptionRepo,
            $campaignRepo,
            new NullLogger(),
        );

        $handler(new EditorialPublishedEvent(
            editorialId: 'editorial-1',
            title: 'Test Article',
            journalistId: 'journalist-1',
        ));
    }

    public function testDiscardsWhenNoEnabledEntities(): void
    {
        $flagChecker = $this->createMock(EntityFlagCheckerInterface::class);
        $subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $campaignRepo = $this->createMock(CampaignRepositoryInterface::class);

        $flagChecker->expects($this->once())
            ->method('getEnabledEntities')
            ->willReturn([]);

        $subscriptionRepo->expects($this->never())->method('hasActiveSubscriptionsForAny');
        $campaignRepo->expects($this->never())->method('save');

        $handler = new EditorialPublishedHandler(
            $flagChecker,
            $subscriptionRepo,
            $campaignRepo,
            new NullLogger(),
        );

        $handler(new EditorialPublishedEvent(
            editorialId: 'editorial-1',
            title: 'Test Article',
            journalistId: 'journalist-1',
            tagIds: ['tag-1'],
        ));
    }

    public function testDiscardsWhenEventHasNoEntities(): void
    {
        $flagChecker = $this->createMock(EntityFlagCheckerInterface::class);
        $subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $campaignRepo = $this->createMock(CampaignRepositoryInterface::class);

        $flagChecker->expects($this->once())
            ->method('getEnabledEntities')
            ->willReturn([]);

        $subscriptionRepo->expects($this->never())->method('hasActiveSubscriptionsForAny');
        $campaignRepo->expects($this->never())->method('save');

        $handler = new EditorialPublishedHandler(
            $flagChecker,
            $subscriptionRepo,
            $campaignRepo,
            new NullLogger(),
        );

        $handler(new EditorialPublishedEvent(
            editorialId: 'editorial-1',
            title: 'Test Article',
        ));
    }
}
