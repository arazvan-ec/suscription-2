<?php

declare(strict_types=1);

namespace App\Tests\Application\Command;

use App\Application\Command\SubscribeCommand;
use App\Application\Command\SubscribeHandler;
use App\Domain\Entity\Subscription;
use App\Domain\Event\SubscriptionCreatedEvent;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\EntityType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SubscribeHandlerTest extends TestCase
{
    public function testSubscribeCreatesNewSubscription(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->with('user-1', EntityType::JOURNALIST, 'journalist-1')
            ->willReturn(null);

        $repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Subscription::class));

        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SubscriptionCreatedEvent::class))
            ->willReturn(new Envelope(new \stdClass()));

        $handler = new SubscribeHandler($repository, $messageBus);

        $handler(new SubscribeCommand(
            'user-1',
            'user@test.com',
            EntityType::JOURNALIST,
            'journalist-1',
        ));
    }

    public function testSubscribeReactivatesInactiveSubscription(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $existing = Subscription::create('user-1', 'user@test.com', EntityType::JOURNALIST, 'journalist-1');
        $existing->deactivate();

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->willReturn($existing);

        $repository->expects($this->once())
            ->method('save');

        // No event dispatched for reactivation
        $messageBus->expects($this->never())->method('dispatch');

        $handler = new SubscribeHandler($repository, $messageBus);

        $handler(new SubscribeCommand(
            'user-1',
            'user@test.com',
            EntityType::JOURNALIST,
            'journalist-1',
        ));

        $this->assertTrue($existing->isActive());
    }

    public function testSubscribeIgnoresAlreadyActiveSubscription(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $existing = Subscription::create('user-1', 'user@test.com', EntityType::JOURNALIST, 'journalist-1');

        $repository->expects($this->once())
            ->method('findByUserAndEntity')
            ->willReturn($existing);

        $repository->expects($this->never())->method('save');
        $messageBus->expects($this->never())->method('dispatch');

        $handler = new SubscribeHandler($repository, $messageBus);

        $handler(new SubscribeCommand(
            'user-1',
            'user@test.com',
            EntityType::JOURNALIST,
            'journalist-1',
        ));
    }
}
