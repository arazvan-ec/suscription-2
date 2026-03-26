<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Domain\Entity\Campaign;
use App\Domain\Event\EditorialPublishedEvent;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Infrastructure\Messenger\EditorialPublishedHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class EditorialPublishedHandlerTest extends TestCase
{
    public function testCreatesCampaignWhenEntitiesHaveFollowers(): void
    {
        $subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $campaignRepo = $this->createMock(CampaignRepositoryInterface::class);

        $subscriptionRepo->expects($this->once())
            ->method('hasActiveSubscriptionsForAny')
            ->willReturn(true);

        $campaignRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Campaign::class));

        $handler = new EditorialPublishedHandler(
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
        $subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $campaignRepo = $this->createMock(CampaignRepositoryInterface::class);

        $subscriptionRepo->expects($this->once())
            ->method('hasActiveSubscriptionsForAny')
            ->willReturn(false);

        $campaignRepo->expects($this->never())->method('save');

        $handler = new EditorialPublishedHandler(
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

    public function testDiscardsWhenNoEntities(): void
    {
        $subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $campaignRepo = $this->createMock(CampaignRepositoryInterface::class);

        $subscriptionRepo->expects($this->never())->method('hasActiveSubscriptionsForAny');
        $campaignRepo->expects($this->never())->method('save');

        $handler = new EditorialPublishedHandler(
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
