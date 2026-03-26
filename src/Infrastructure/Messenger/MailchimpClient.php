<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MailchimpClient
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

        $this->httpClient->request('PUT', $this->buildUrl("/lists/{$this->mailchimpListId}/members/{$subscriberHash}"), [
            'json' => [
                'email_address' => $email,
                'status_if_new' => 'subscribed',
                'status' => 'subscribed',
                'tags' => $tags,
            ],
            'headers' => $this->buildHeaders(),
        ]);

        $this->logger->info('Added to Mailchimp audience', ['email' => $email]);
    }

    public function removeFromAudience(string $email): void
    {
        $subscriberHash = md5(strtolower($email));

        $this->httpClient->request('PATCH', $this->buildUrl("/lists/{$this->mailchimpListId}/members/{$subscriberHash}"), [
            'json' => [
                'status' => 'unsubscribed',
            ],
            'headers' => $this->buildHeaders(),
        ]);

        $this->logger->info('Removed from Mailchimp audience', ['email' => $email]);
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
