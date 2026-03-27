<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Port\EntityFlagCheckerInterface;
use App\Domain\Event\EditorialPublishedEvent;
use App\Domain\ValueObject\EntityType;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class EntityFlagChecker implements EntityFlagCheckerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $journalistServiceUrl,
        private string $tagServiceUrl,
        private string $sectionServiceUrl,
        private LoggerInterface $logger,
    ) {}

    public function getEnabledEntities(EditorialPublishedEvent $event): array
    {
        $entities = [];

        if ($event->journalistId !== null) {
            if ($this->isFollowEnabled(EntityType::JOURNALIST, $event->journalistId, $this->journalistServiceUrl)) {
                $entities[] = ['type' => EntityType::JOURNALIST, 'id' => $event->journalistId];
            }
        }

        foreach ($event->tagIds as $tagId) {
            if ($this->isFollowEnabled(EntityType::TAG, $tagId, $this->tagServiceUrl)) {
                $entities[] = ['type' => EntityType::TAG, 'id' => $tagId];
            }
        }

        if ($event->sectionId !== null) {
            if ($this->isFollowEnabled(EntityType::SECTION, $event->sectionId, $this->sectionServiceUrl)) {
                $entities[] = ['type' => EntityType::SECTION, 'id' => $event->sectionId];
            }
        }

        return $entities;
    }

    private function isFollowEnabled(EntityType $type, string $id, string $serviceUrl): bool
    {
        try {
            $safeId = urlencode($id);
            $response = $this->httpClient->request('GET', "{$serviceUrl}/{$safeId}", [
                'timeout' => 2,
            ]);

            $data = $response->toArray();

            return (bool) ($data['follow'] ?? false);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to check follow flag, skipping entity', [
                'type' => $type->value,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
