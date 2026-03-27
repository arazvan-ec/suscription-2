<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Application\Command\ProcessCampaignCommand;
use App\Application\Command\SendNotificationCommand;
use App\Domain\Entity\Campaign;
use App\Domain\Entity\Subscription;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\CampaignType;
use App\Domain\ValueObject\EntityType;
use App\Infrastructure\Messenger\ProcessCampaignHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ProcessCampaignHandlerTest extends TestCase
{
    private CampaignRepositoryInterface $campaignRepo;
    private SubscriptionRepositoryInterface $subscriptionRepo;
    private MessageBusInterface $messageBus;
    private ProcessCampaignHandler $handler;

    protected function setUp(): void
    {
        $this->campaignRepo = $this->createMock(CampaignRepositoryInterface::class);
        $this->subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new ProcessCampaignHandler(
            $this->campaignRepo,
            $this->subscriptionRepo,
            $this->messageBus,
            new NullLogger(),
        );
    }

    public function testProcessesCampaignAndDispatchesNotification(): void
    {
        $campaign = Campaign::create(
            CampaignType::EDITORIAL_NOTIFICATION,
            'editorial-1',
            [['type' => EntityType::JOURNALIST, 'id' => 'journalist-1']],
            new \DateTimeImmutable('-1 hour'),
        );

        $sub1 = Subscription::create('user-1', 'user1@example.com', EntityType::JOURNALIST, 'journalist-1');
        $sub2 = Subscription::create('user-2', 'user2@example.com', EntityType::JOURNALIST, 'journalist-1');

        $this->campaignRepo->method('find')->willReturn($campaign);
        $this->campaignRepo->expects($this->atLeast(2))->method('save');

        $this->subscriptionRepo->method('findActiveByEntities')->willReturn([$sub1, $sub2]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendNotificationCommand $cmd) {
                return $cmd->editorialId === 'editorial-1'
                    && count($cmd->recipients) === 2
                    && $cmd->channel === 'email';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)(new ProcessCampaignCommand((string) $campaign->getId()));

        $this->assertSame('sent', $campaign->getStatus()->value);
    }

    public function testMarksSentWhenNoSubscribers(): void
    {
        $campaign = Campaign::create(
            CampaignType::EDITORIAL_NOTIFICATION,
            'editorial-1',
            [['type' => EntityType::JOURNALIST, 'id' => 'journalist-1']],
            new \DateTimeImmutable('-1 hour'),
        );

        $this->campaignRepo->method('find')->willReturn($campaign);
        $this->campaignRepo->expects($this->atLeast(2))->method('save');

        $this->subscriptionRepo->method('findActiveByEntities')->willReturn([]);

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)(new ProcessCampaignCommand((string) $campaign->getId()));

        $this->assertSame('sent', $campaign->getStatus()->value);
    }

    public function testMarksFailedOnException(): void
    {
        $campaign = Campaign::create(
            CampaignType::EDITORIAL_NOTIFICATION,
            'editorial-1',
            [['type' => EntityType::JOURNALIST, 'id' => 'journalist-1']],
            new \DateTimeImmutable('-1 hour'),
        );

        $this->campaignRepo->method('find')->willReturn($campaign);
        $this->campaignRepo->expects($this->atLeast(2))->method('save');

        $this->subscriptionRepo->method('findActiveByEntities')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        ($this->handler)(new ProcessCampaignCommand((string) $campaign->getId()));

        $this->assertSame('failed', $campaign->getStatus()->value);
    }

    public function testSkipsNonPendingCampaign(): void
    {
        $campaign = Campaign::create(
            CampaignType::EDITORIAL_NOTIFICATION,
            'editorial-1',
            [['type' => EntityType::JOURNALIST, 'id' => 'journalist-1']],
            new \DateTimeImmutable('-1 hour'),
        );
        $campaign->markAsProcessing();
        $campaign->markAsSent();

        $this->campaignRepo->method('find')->willReturn($campaign);
        $this->campaignRepo->expects($this->never())->method('save');
        $this->subscriptionRepo->expects($this->never())->method('findActiveByEntities');
        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)(new ProcessCampaignCommand((string) $campaign->getId()));
    }

    public function testDeduplicatesRecipientsByEmail(): void
    {
        $campaign = Campaign::create(
            CampaignType::EDITORIAL_NOTIFICATION,
            'editorial-1',
            [
                ['type' => EntityType::JOURNALIST, 'id' => 'journalist-1'],
                ['type' => EntityType::TAG, 'id' => 'tag-1'],
            ],
            new \DateTimeImmutable('-1 hour'),
        );

        // Same email, different subscriptions (user subscribed to both journalist and tag)
        $sub1 = Subscription::create('user-1', 'same@example.com', EntityType::JOURNALIST, 'journalist-1');
        $sub2 = Subscription::create('user-1', 'same@example.com', EntityType::TAG, 'tag-1');
        $sub3 = Subscription::create('user-2', 'other@example.com', EntityType::JOURNALIST, 'journalist-1');

        $this->campaignRepo->method('find')->willReturn($campaign);
        $this->campaignRepo->expects($this->atLeast(2))->method('save');

        $this->subscriptionRepo->method('findActiveByEntities')->willReturn([$sub1, $sub2, $sub3]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendNotificationCommand $cmd) {
                return count($cmd->recipients) === 2;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)(new ProcessCampaignCommand((string) $campaign->getId()));
    }
}
