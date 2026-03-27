<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Serializer;

use App\Domain\Event\EditorialPublishedEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class JarvisEventSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        $body = $encodedEnvelope['body'] ?? throw new UnrecoverableMessageHandlingException(
            'Missing message body.',
        );

        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UnrecoverableMessageHandlingException(
                sprintf('Failed to decode JSON: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $event = new EditorialPublishedEvent(
            editorialId: $data['editorial_id'] ?? throw new UnrecoverableMessageHandlingException(
                'Missing required field: editorial_id',
            ),
            title: $data['title'] ?? '',
            journalistId: $data['journalist_id'] ?? null,
            tagIds: $data['tag_ids'] ?? [],
            sectionId: $data['section_id'] ?? null,
            occurredAt: isset($data['occurred_at'])
                ? $this->parseDate($data['occurred_at'])
                : new \DateTimeImmutable(),
        );

        return new Envelope($event);
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        if (!$message instanceof EditorialPublishedEvent) {
            throw new \InvalidArgumentException(sprintf(
                'JarvisEventSerializer can only encode %s, got %s',
                EditorialPublishedEvent::class,
                get_class($message),
            ));
        }

        return [
            'body' => json_encode([
                'editorial_id' => $message->editorialId,
                'title' => $message->title,
                'journalist_id' => $message->journalistId,
                'tag_ids' => $message->tagIds,
                'section_id' => $message->sectionId,
                'occurred_at' => $message->occurredAt->format('c'),
            ], \JSON_THROW_ON_ERROR),
            'headers' => ['Content-Type' => 'application/json'],
        ];
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new UnrecoverableMessageHandlingException(
                sprintf('Invalid date format for occurred_at: %s', $value),
                0,
                $e,
            );
        }
    }
}
