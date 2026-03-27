<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Port\EntityFlagCheckerInterface;
use App\Domain\Entity\Campaign;
use App\Domain\Event\EditorialPublishedEvent;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\CampaignType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'jarvis_events')]
final readonly class EditorialPublishedHandler
{
    public function __construct(
        private EntityFlagCheckerInterface $flagChecker,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private CampaignRepositoryInterface $campaignRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(EditorialPublishedEvent $event): void
    {
        $this->logger->info('Processing editorial.published', [
            'editorial_id' => $event->editorialId,
        ]);

        $entities = $this->flagChecker->getEnabledEntities($event);

        if (empty($entities)) {
            $this->logger->info('No enabled entities found for editorial, discarding', [
                'editorial_id' => $event->editorialId,
            ]);
            return;
        }

        $hasFollowers = $this->subscriptionRepository->hasActiveSubscriptionsForAny($entities);

        if (!$hasFollowers) {
            $this->logger->info('No followers for any entity, discarding', [
                'editorial_id' => $event->editorialId,
            ]);
            return;
        }

        $campaign = Campaign::create(
            type: CampaignType::EDITORIAL_NOTIFICATION,
            editorialId: $event->editorialId,
            audienceCriteria: $entities,
            scheduledAt: new \DateTimeImmutable('+5 minutes'),
        );

        $this->campaignRepository->save($campaign);

        $this->logger->info('Campaign created', [
            'campaign_id' => (string) $campaign->getId(),
            'editorial_id' => $event->editorialId,
            'scheduled_at' => $campaign->getScheduledAt()->format('c'),
            'entity_count' => count($entities),
        ]);
    }
}
