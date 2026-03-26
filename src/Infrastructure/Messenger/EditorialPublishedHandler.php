<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Domain\Entity\Campaign;
use App\Domain\Event\EditorialPublishedEvent;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\CampaignType;
use App\Domain\ValueObject\EntityType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async')]
final readonly class EditorialPublishedHandler
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private CampaignRepositoryInterface $campaignRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(EditorialPublishedEvent $event): void
    {
        $this->logger->info('Processing editorial.published', [
            'editorial_id' => $event->editorialId,
        ]);

        $entities = $this->buildEntityList($event);

        if (empty($entities)) {
            $this->logger->info('No entities found for editorial, discarding', [
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

    private function buildEntityList(EditorialPublishedEvent $event): array
    {
        $entities = [];

        if ($event->journalistId !== null) {
            $entities[] = ['type' => EntityType::JOURNALIST, 'id' => $event->journalistId];
        }

        foreach ($event->tagIds as $tagId) {
            $entities[] = ['type' => EntityType::TAG, 'id' => $tagId];
        }

        if ($event->sectionId !== null) {
            $entities[] = ['type' => EntityType::SECTION, 'id' => $event->sectionId];
        }

        return $entities;
    }
}
