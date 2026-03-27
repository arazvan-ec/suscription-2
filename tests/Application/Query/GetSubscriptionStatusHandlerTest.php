<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Query\GetSubscriptionStatusHandler;
use App\Application\Query\GetSubscriptionStatusQuery;
use App\Domain\Entity\Subscription;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\EntityType;
use PHPUnit\Framework\TestCase;

final class GetSubscriptionStatusHandlerTest extends TestCase
{
    public function testReturnsTrueWhenActiveSubscriptionExists(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);

        $subscription = Subscription::create('user-1', 'user@test.com', EntityType::JOURNALIST, 'journalist-1');

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->with('user-1', EntityType::JOURNALIST, 'journalist-1')
            ->willReturn($subscription);

        $handler = new GetSubscriptionStatusHandler($repository);

        $result = $handler(new GetSubscriptionStatusQuery('user-1', EntityType::JOURNALIST, 'journalist-1'));

        $this->assertTrue($result);
    }

    public function testReturnsFalseWhenNoSubscriptionExists(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->willReturn(null);

        $handler = new GetSubscriptionStatusHandler($repository);

        $result = $handler(new GetSubscriptionStatusQuery('user-1', EntityType::TAG, 'tag-1'));

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenSubscriptionIsInactive(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);

        $subscription = Subscription::create('user-1', 'user@test.com', EntityType::JOURNALIST, 'journalist-1');
        $subscription->deactivate();

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->willReturn($subscription);

        $handler = new GetSubscriptionStatusHandler($repository);

        $result = $handler(new GetSubscriptionStatusQuery('user-1', EntityType::JOURNALIST, 'journalist-1'));

        $this->assertFalse($result);
    }
}
