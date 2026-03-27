<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger\Serializer;

use App\Domain\Event\EditorialPublishedEvent;
use App\Infrastructure\Messenger\Serializer\JarvisEventSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class JarvisEventSerializerTest extends TestCase
{
    private JarvisEventSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JarvisEventSerializer();
    }

    public function testDecodeWithValidJson(): void
    {
        $body = json_encode([
            'editorial_id' => 'editorial-123',
            'title' => 'Breaking News',
            'journalist_id' => 'journalist-456',
            'tag_ids' => ['tag-1', 'tag-2'],
            'section_id' => 'section-789',
            'occurred_at' => '2026-03-26T10:00:00+00:00',
        ]);

        $envelope = $this->serializer->decode(['body' => $body]);

        $event = $envelope->getMessage();
        $this->assertInstanceOf(EditorialPublishedEvent::class, $event);
        $this->assertSame('editorial-123', $event->editorialId);
        $this->assertSame('Breaking News', $event->title);
        $this->assertSame('journalist-456', $event->journalistId);
        $this->assertSame(['tag-1', 'tag-2'], $event->tagIds);
        $this->assertSame('section-789', $event->sectionId);
    }

    public function testDecodeHandlesMissingOptionalFields(): void
    {
        $body = json_encode([
            'editorial_id' => 'editorial-123',
        ]);

        $envelope = $this->serializer->decode(['body' => $body]);

        $event = $envelope->getMessage();
        $this->assertInstanceOf(EditorialPublishedEvent::class, $event);
        $this->assertSame('editorial-123', $event->editorialId);
        $this->assertSame('', $event->title);
        $this->assertNull($event->journalistId);
        $this->assertSame([], $event->tagIds);
        $this->assertNull($event->sectionId);
    }

    public function testDecodeThrowsOnInvalidJson(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessageMatches('/Failed to decode JSON/');

        $this->serializer->decode(['body' => 'not valid json {{{']);
    }

    public function testDecodeThrowsOnMissingBody(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Missing message body');

        $this->serializer->decode([]);
    }
}
