<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Command\ProcessCampaignCommand;
use App\Application\Command\SendNotificationCommand;
use App\Domain\Entity\Subscription;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ProcessCampaignHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaignRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessCampaignCommand $command): void
    {
        $campaign = $this->campaignRepository->find($command->campaignId);

        if ($campaign === null) {
            $this->logger->warning('Campaign not found', ['campaign_id' => $command->campaignId]);
            return;
        }

        if (!$campaign->isPending()) {
            $this->logger->info('Campaign already processed', [
                'campaign_id' => $command->campaignId,
                'status' => $campaign->getStatus()->value,
            ]);
            return;
        }

        $campaign->markAsProcessing();
        $this->campaignRepository->save($campaign);

        try {
            $subscribers = $this->subscriptionRepository->findActiveByEntities(
                $campaign->getAudienceCriteria()
            );

            if (empty($subscribers)) {
                $this->logger->info('No active subscribers for campaign', [
                    'campaign_id' => $command->campaignId,
                ]);
                $campaign->markAsSent();
                $this->campaignRepository->save($campaign);
                return;
            }

            $recipients = array_map(
                fn (Subscription $s) => ['email' => $s->getEmail(), 'user_id' => $s->getUserId()],
                $subscribers,
            );

            // Deduplicate by email
            $uniqueRecipients = [];
            foreach ($recipients as $recipient) {
                $uniqueRecipients[$recipient['email']] = $recipient;
            }

            $this->messageBus->dispatch(new SendNotificationCommand(
                recipients: array_values($uniqueRecipients),
                editorialId: $campaign->getEditorialId(),
                editorialTitle: '', // Enriched by notifier-service
                channel: 'email',
            ));

            $campaign->markAsSent();
            $this->campaignRepository->save($campaign);

            $this->logger->info('Campaign dispatched to notifier', [
                'campaign_id' => $command->campaignId,
                'recipient_count' => count($uniqueRecipients),
            ]);
        } catch (\Throwable $e) {
            $campaign->markAsFailed();
            $this->campaignRepository->save($campaign);

            $this->logger->error('Campaign processing failed', [
                'campaign_id' => $command->campaignId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
