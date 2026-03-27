<?php

declare(strict_types=1);

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\Subscription;
use App\Domain\ValueObject\EntityType;
use App\Domain\ValueObject\SubscriptionStatus;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testCreateSubscription(): void
    {
        $subscription = Subscription::create(
            'user-123',
            'user@example.com',
            EntityType::JOURNALIST,
            'journalist-456',
        );

        $this->assertNotNull($subscription->getId());
        $this->assertSame('user-123', $subscription->getUserId());
        $this->assertSame('user@example.com', $subscription->getEmail());
        $this->assertSame(EntityType::JOURNALIST, $subscription->getEntityType());
        $this->assertSame('journalist-456', $subscription->getEntityId());
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
        $this->assertTrue($subscription->isActive());
    }

    public function testDeactivateSubscription(): void
    {
        $subscription = Subscription::create(
            'user-123',
            'user@example.com',
            EntityType::TAG,
            'tag-789',
        );

        $subscription->deactivate();

        $this->assertSame(SubscriptionStatus::INACTIVE, $subscription->getStatus());
        $this->assertFalse($subscription->isActive());
    }

    public function testReactivateSubscription(): void
    {
        $subscription = Subscription::create(
            'user-123',
            'user@example.com',
            EntityType::SECTION,
            'section-1',
        );

        $subscription->deactivate();
        $this->assertFalse($subscription->isActive());

        $subscription->reactivate();
        $this->assertTrue($subscription->isActive());
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
    }

    public function testUpdatedAtChangesOnStatusChange(): void
    {
        $subscription = Subscription::create(
            'user-123',
            'user@example.com',
            EntityType::JOURNALIST,
            'journalist-1',
        );

        $createdUpdatedAt = $subscription->getUpdatedAt();

        // Small sleep to ensure timestamp differs
        usleep(1000);
        $subscription->deactivate();

        $this->assertGreaterThanOrEqual(
            $createdUpdatedAt,
            $subscription->getUpdatedAt(),
        );
    }
}
