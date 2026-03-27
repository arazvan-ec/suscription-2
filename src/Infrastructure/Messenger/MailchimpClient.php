<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Port\MailchimpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MailchimpClient implements MailchimpClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $mailchimpApiKey,
        private string $mailchimpListId,
        private string $mailchimpDataCenter,
        private LoggerInterface $logger,
    ) {}

    public function addToAudience(string $email, array $tags = []): void
    {
        $subscriberHash = md5(strtolower($email));

        try {
            $this->httpClient->request('PUT', $this->buildUrl("/lists/{$this->mailchimpListId}/members/{$subscriberHash}"), [
                'json' => [
                    'email_address' => $email,
                    'status_if_new' => 'subscribed',
                ],
                'headers' => $this->buildHeaders(),
            ]);

            if (!empty($tags)) {
                $this->httpClient->request('POST', $this->buildUrl("/lists/{$this->mailchimpListId}/members/{$subscriberHash}/tags"), [
                    'json' => [
                        'tags' => array_map(
                            static fn(string $tag): array => ['name' => $tag, 'status' => 'active'],
                            $tags,
                        ),
                    ],
                    'headers' => $this->buildHeaders(),
                ]);
            }

            $this->logger->info('Added to Mailchimp audience', ['email' => $email, 'tags' => $tags]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to add to Mailchimp audience', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function removeFromAudience(string $email): void
    {
        $subscriberHash = md5(strtolower($email));

        try {
            $this->httpClient->request('PATCH', $this->buildUrl("/lists/{$this->mailchimpListId}/members/{$subscriberHash}"), [
                'json' => [
                    'status' => 'unsubscribed',
                ],
                'headers' => $this->buildHeaders(),
            ]);

            $this->logger->info('Removed from Mailchimp audience', ['email' => $email]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to remove from Mailchimp audience', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildUrl(string $path): string
    {
        return "https://{$this->mailchimpDataCenter}.api.mailchimp.com/3.0{$path}";
    }

    private function buildHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->mailchimpApiKey}",
            'Content-Type' => 'application/json',
        ];
    }
}
