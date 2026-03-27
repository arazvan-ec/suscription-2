<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Command\ProcessCampaignCommand;
use App\Domain\Repository\CampaignRepositoryInterface;
use App\Infrastructure\Scheduler\ProcessPendingCampaignsMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ProcessPendingCampaignsHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaignRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessPendingCampaignsMessage $message): void
    {
        $campaigns = $this->campaignRepository->findReadyToProcess();

        foreach ($campaigns as $campaign) {
            $this->messageBus->dispatch(
                new ProcessCampaignCommand((string) $campaign->getId())
            );
        }

        $this->logger->info('Dispatched pending campaigns for processing', [
            'count' => count($campaigns),
        ]);
    }
}
