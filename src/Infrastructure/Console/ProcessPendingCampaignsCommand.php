<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Command\ProcessCampaignCommand;
use App\Domain\Repository\CampaignRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:campaigns:process',
    description: 'Process pending campaigns that are ready to be sent',
)]
final class ProcessPendingCampaignsCommand extends Command
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $campaigns = $this->campaignRepository->findReadyToProcess();

        if (empty($campaigns)) {
            $io->info('No pending campaigns to process.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d campaigns ready to process.', count($campaigns)));

        foreach ($campaigns as $campaign) {
            $campaignId = (string) $campaign->getId();

            $this->logger->info('Dispatching campaign for processing', [
                'campaign_id' => $campaignId,
            ]);

            $this->messageBus->dispatch(
                new ProcessCampaignCommand($campaignId)
            );

            $io->writeln("  Dispatched: {$campaignId}");
        }

        $io->success(sprintf('Dispatched %d campaigns for processing.', count($campaigns)));

        return Command::SUCCESS;
    }
}
