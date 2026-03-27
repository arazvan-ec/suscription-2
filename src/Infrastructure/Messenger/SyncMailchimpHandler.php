<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Command\SyncMailchimpCommand;
use App\Application\Port\MailchimpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'mailchimp_sync')]
final readonly class SyncMailchimpHandler
{
    public function __construct(
        private MailchimpClientInterface $mailchimpClient,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SyncMailchimpCommand $command): void
    {
        $this->logger->info('Syncing with Mailchimp', [
            'email' => $command->email,
            'action' => $command->action,
        ]);

        match ($command->action) {
            SyncMailchimpCommand::ACTION_SUBSCRIBE => $this->mailchimpClient->addToAudience(
                $command->email,
                $command->tags,
            ),
            SyncMailchimpCommand::ACTION_REMOVE_TAGS => $this->mailchimpClient->removeTagsFromAudience(
                $command->email,
                $command->tags,
            ),
            SyncMailchimpCommand::ACTION_UNSUBSCRIBE => $this->mailchimpClient->removeFromAudience(
                $command->email,
            ),
            default => throw new \Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException(
                "Unknown Mailchimp sync action: {$command->action}",
            ),
        };

        $this->logger->info('Mailchimp sync completed', [
            'email' => $command->email,
            'action' => $command->action,
        ]);
    }
}
