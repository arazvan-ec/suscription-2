<?php

declare(strict_types=1);

namespace App\Tests\Application\Command;

use App\Application\Command\UnsubscribeCommand;
use App\Application\Command\UnsubscribeHandler;
use App\Domain\Entity\Subscription;
use App\Domain\Event\SubscriptionDeletedEvent;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\EntityType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class UnsubscribeHandlerTest extends TestCase
{
    public function testUnsubscribeDeactivatesSubscription(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $existing = Subscription::create('user-1', 'user@test.com', EntityType::JOURNALIST, 'journalist-1');

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->with('user-1', EntityType::JOURNALIST, 'journalist-1')
            ->willReturn($existing);

        $repository->expects($this->once())->method('save');

        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SubscriptionDeletedEvent::class))
            ->willReturn(new Envelope(new \stdClass()));

        $handler = new UnsubscribeHandler($repository, $messageBus);

        $handler(new UnsubscribeCommand('user-1', EntityType::JOURNALIST, 'journalist-1'));

        $this->assertFalse($existing->isActive());
    }

    public function testUnsubscribeIgnoresNonExistentSubscription(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->willReturn(null);

        $repository->expects($this->never())->method('save');
        $messageBus->expects($this->never())->method('dispatch');

        $handler = new UnsubscribeHandler($repository, $messageBus);

        $handler(new UnsubscribeCommand('user-1', EntityType::JOURNALIST, 'journalist-1'));
    }

    public function testUnsubscribeIgnoresAlreadyInactiveSubscription(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $existing = Subscription::create('user-1', 'user@test.com', EntityType::JOURNALIST, 'journalist-1');
        $existing->deactivate();

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->willReturn($existing);

        $repository->expects($this->never())->method('save');
        $messageBus->expects($this->never())->method('dispatch');

        $handler = new UnsubscribeHandler($repository, $messageBus);

        $handler(new UnsubscribeCommand('user-1', EntityType::JOURNALIST, 'journalist-1'));
    }
}
