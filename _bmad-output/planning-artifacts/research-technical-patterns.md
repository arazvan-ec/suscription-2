# Technical Research: enBandeja Patterns

**Date**: 2026-03-26
**Status**: Complete
**Scope**: Mailchimp sync, campaign scheduling, external event consumption

---

## Topic 1: Mailchimp Marketing API — Audience Sync Patterns

### Summary

Mailchimp Marketing API v3 enforces a 10-concurrent-connection cap per API key (not a per-second rate). The API provides both individual member upsert (`PUT`) and a batch operations endpoint (`POST /batches`) for bulk work. Tags must be managed via a dedicated endpoint, separate from the member upsert call.

### Options Compared

#### Option A: Individual Upsert per Subscription Event

Each subscribe/unsubscribe dispatches one async message that calls `PUT /lists/{list_id}/members/{subscriber_hash}` followed by `POST .../members/{subscriber_hash}/tags`.

- **Pros**: Simple, real-time, easy error tracking per member.
- **Cons**: At scale (200+ events/min during peaks), risks hitting the 10-connection cap. Each subscription change = 2 API calls (upsert + tags).

#### Option B: Batch Operations Endpoint (`POST /batches`)

Accumulate changes over a time window (e.g., 30s), then submit a single batch with up to 500 operations. Mailchimp processes these asynchronously on their infrastructure.

- **Pros**: Bypasses concurrent connection limits. Efficient for bulk operations.
- **Cons**: Fire-and-forget; results require polling `GET /batches/{batch_id}`. Operations are not guaranteed to execute in order. Harder to track per-member errors.

#### Option C: Hybrid — Individual for Real-Time, Batch for Backfill

Use individual upsert (Option A) for live subscribe/unsubscribe events via Messenger queue with concurrency=5. Use batch endpoint (Option B) for initial data migration or reconciliation jobs.

- **Pros**: Best latency for user-facing actions; efficient for bulk.
- **Cons**: Two code paths to maintain.

### Recommendation: Option C (Hybrid)

Rationale: Live subscription events are low-volume (brainstorming report estimates ~50K total subscriptions, not per day). A dedicated Messenger transport with `prefetch_count=5` keeps well under the 10-connection cap. The batch endpoint is reserved for migration/reconciliation.

### Key API Patterns

**Upsert member** — `PUT /lists/{list_id}/members/{subscriber_hash}`

```
subscriber_hash = md5(strtolower($email))
```

Use `status_if_new: "subscribed"` (not `status`) to avoid resubscribing users who previously unsubscribed from Mailchimp directly.

**Manage tags** — `POST /lists/{list_id}/members/{subscriber_hash}/tags`

```json
{
  "tags": [
    {"name": "journalist:456", "status": "active"},
    {"name": "tag:789", "status": "active"}
  ]
}
```

Tag naming convention: `{entity_type}:{entity_id}` enables segmentation by entity.

**Error handling when Mailchimp is down:**

1. Messenger retry strategy handles transient failures (3 retries, exponential backoff).
2. After max retries, messages land in the `failed` transport (Doctrine).
3. Implement a **client-side circuit breaker** via a shared cache flag: if N consecutive Mailchimp calls fail within a window, pause the Mailchimp sync queue and alert. Subscriptions still work locally (DB is source of truth); sync catches up when Mailchimp recovers.
4. A reconciliation command (`app:mailchimp:reconcile`) can be run on-demand to fix drift.

### Sources

- [Mailchimp Fundamentals — Rate Limits](https://mailchimp.com/developer/marketing/docs/fundamentals/)
- [Add or Update List Member (PUT upsert)](https://mailchimp.com/developer/marketing/api/list-members/add-or-update-list-member/)
- [Batch Subscribe or Unsubscribe](https://mailchimp.com/developer/marketing/api/lists/batch-subscribe-or-unsubscribe/)
- [Batch Operations Endpoint](https://mailchimp.com/developer/marketing/api/batch-operations/)
- [Organize Contacts with Tags](https://mailchimp.com/developer/marketing/guides/organize-contacts-with-tags/)
- [Mailchimp Error Reference](https://mailchimp.com/developer/marketing/docs/errors/)

---

## Topic 2: Campaign Scheduling — Worker vs Cron vs Scheduler

### Summary

The campaign processing requirement is: "find campaigns where `scheduled_at <= NOW()` and `status = pending`, then process them." This is a polling pattern that must handle exactly-once semantics when scaling to multiple worker instances. Three approaches exist in the Symfony ecosystem.

### Options Compared

#### Option A: Cron + Console Command

A system cron runs `bin/console app:process-campaigns` every minute. The command queries for pending campaigns and dispatches `ProcessCampaignCommand` messages.

- **Pros**: Simple, well-understood, no new dependencies.
- **Cons**: 1-minute minimum granularity. Race conditions with multiple pods require `LockableTrait` or database-level `SELECT ... FOR UPDATE SKIP LOCKED`. Crontab management is painful in containers.

#### Option B: Symfony Scheduler Component

Define a recurring schedule that dispatches `ProcessPendingCampaignsMessage` every 60 seconds, processed via Messenger.

```php
#[AsSchedule('campaigns')]
final class CampaignScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('60 seconds', new ProcessPendingCampaignsMessage()))
            ->stateful($this->cache)
            ->lock($this->lockFactory->createLock('campaign-scheduler'));
    }
}
```

- **Pros**: No external cron needed. Built-in `->stateful()` + `->lock()` prevents duplicate execution across workers. Missed-run recovery. Sub-minute precision if needed later. Container-friendly.
- **Cons**: Requires shared lock store (Redis or database) for multi-pod. The scheduler transport must be consumed alongside other transports.

#### Option C: Persistent Worker Polling the Database Directly

A long-running Messenger worker continuously queries the database for pending campaigns via a custom transport or periodic handler.

- **Pros**: Instant reaction, no polling interval.
- **Cons**: Constant DB pressure. Complex to implement correctly. No advantage over Scheduler for a 60s interval use case.

### Recommendation: Option B (Symfony Scheduler)

Rationale: The project already uses Symfony Messenger and RabbitMQ. The Scheduler component integrates natively with Messenger (since Symfony 7.2, `messenger:consume` handles scheduled messages). The `->stateful()` + `->lock()` combination solves exactly-once across pods without custom code. The 5-minute delay tolerance for campaigns (per brainstorming report) means 60-second polling is more than adequate.

### Preventing Double-Processing

The Scheduler lock prevents duplicate *dispatch*. To also prevent duplicate *processing* of the same campaign (idempotency), use a two-layer approach:

1. **Scheduler lock**: Ensures only one worker dispatches `ProcessPendingCampaignsMessage` per interval.
2. **Pessimistic DB lock in the handler**: `SELECT ... WHERE status = 'pending' AND scheduled_at <= NOW() FOR UPDATE SKIP LOCKED` ensures that if two messages somehow arrive, each campaign row is claimed by exactly one handler.
3. **Status transition**: Move campaign from `pending` -> `processing` -> `sent` atomically. The handler checks current status before acting.

### Sources

- [Symfony Scheduler Docs](https://symfony.com/doc/current/scheduler.html)
- [Symfony Scheduler — How it Really Works](https://medium.com/@fico7489/symfony-scheduler-how-it-really-works-ef5d95409c09)
- [Scheduler duplicate execution issue #58945](https://github.com/symfony/symfony/issues/58945)
- [Scheduler Lock with multiple PODs](https://github.com/symfony/symfony/discussions/57889)
- [LockableTrait for Console Commands](https://symfony.com/doc/current/console/lockable_trait.html)

---

## Topic 3: Event Consumption — External Events from Jarvis CMS

### Summary

Jarvis CMS publishes `editorial.published` events to RabbitMQ in a plain JSON format without Symfony Messenger envelope headers. Symfony Messenger's default `PhpSerializer` and `SymfonySerializer` both fail on these messages because they expect a `type` header in the AMQP message properties. A custom serializer is required.

### Options Compared

#### Option A: Custom Transport Serializer

Implement `Symfony\Component\Messenger\Transport\Serialization\SerializerInterface` with `decode()` and `encode()` methods. The `decode()` method JSON-decodes the raw body, maps it to the appropriate message class, and returns an `Envelope`.

- **Pros**: Full control over deserialization. Can handle any external format. Well-documented pattern.
- **Cons**: Must handle stamps manually for retry/redelivery. Must be resilient to schema changes.

#### Option B: RabbitMQ Shovel/Bridge to Normalized Queue

Use RabbitMQ Shovel plugin to copy messages from Jarvis's exchange to a local exchange, applying a transformation. Symfony consumes the normalized messages.

- **Pros**: Decouples from Jarvis's message format at the infrastructure level.
- **Cons**: Adds infrastructure complexity. Shovel has limited transformation capabilities. Still need a custom serializer if the format is non-standard.

#### Option C: Middleware-Based Approach

Use Messenger middleware to intercept and transform messages before they reach handlers.

- **Pros**: Keeps serializer simple.
- **Cons**: Middleware runs after deserialization, so it cannot solve the fundamental deserialization problem.

### Recommendation: Option A (Custom Transport Serializer)

Rationale: It is the standard Symfony approach, well-documented, and gives full control. The serializer is configured per-transport, so it only affects the Jarvis consumer queue.

### Implementation Pattern

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            jarvis_events:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                serializer: App\Infrastructure\Messenger\JarvisEventSerializer
                options:
                    exchange:
                        name: jarvis
                        type: topic
                    queues:
                        enbandeja_editorial:
                            binding_keys: ['editorial.published']
```

```php
final class JarvisEventSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        $body = json_decode($encodedEnvelope['body'], true, 512, JSON_THROW_ON_ERROR);

        $event = new EditorialPublishedEvent(
            editorialId: $body['editorial_id'],
            title: $body['title'] ?? '',
            journalists: $body['journalists'] ?? [],
            tags: $body['tags'] ?? [],
            sections: $body['sections'] ?? [],
            publishedAt: new \DateTimeImmutable($body['published_at']),
        );

        return new Envelope($event);
    }

    public function encode(Envelope $envelope): array
    {
        // Only needed if we send messages back; for consume-only, minimal impl
        $message = $envelope->last(TransportMessageIdStamp::class);
        return [
            'body' => json_encode($envelope->getMessage(), JSON_THROW_ON_ERROR),
            'headers' => ['Content-Type' => 'application/json'],
        ];
    }
}
```

### Schema Evolution Strategy

Jarvis may add new fields over time. The serializer must be resilient:

1. **Use null coalescing / defaults**: `$body['new_field'] ?? null` ensures new fields don't break existing code.
2. **Ignore unknown fields**: Never use strict deserialization; only extract known fields.
3. **Version detection**: If Jarvis introduces breaking changes, check for a `version` or `schema_version` field. If absent, assume v1.
4. **Logging**: Log the raw body at DEBUG level for troubleshooting schema mismatches. Log warnings for missing expected fields.

### Retry and Dead-Letter Strategy

```yaml
# Transport-level retry with exponential backoff
jarvis_events:
    retry_strategy:
        max_retries: 3
        delay: 1000
        multiplier: 3
        max_delay: 30000
```

**Three-tier failure handling:**

| Tier | Condition | Action |
|------|-----------|--------|
| Retry | Transient error (HTTP timeout to flag service, DB lock) | Automatic retry via Messenger (3x, exponential) |
| Dead letter | Persistent failure after retries | Route to `failed` transport (Doctrine) for inspection |
| Poison message | Unparseable JSON, schema mismatch | Catch in serializer `decode()`, log error, wrap in `RejectedMessage` and send to dead letter. Throw `UnrecoverableMessageHandlingException` to skip retries. |

The `failed` transport uses Doctrine (not RabbitMQ) so failed messages can be listed, inspected, and retried via `messenger:failed:show` and `messenger:failed:retry` commands.

### Sources

- [Symfony Messenger Docs — Custom Serializers](https://symfony.com/doc/current/messenger.html)
- [SymfonyCasts: Custom Transport Serializer](https://symfonycasts.com/screencast/messenger/transport-serializer)
- [Consume External Messages Using Symfony Messenger](https://medium.com/@sfmok/consume-external-messages-using-symfony-messenger-92f7490d1194)
- [Symfony Messenger and Interoperability — JoliCode](https://jolicode.com/blog/about-symfony-messenger-and-interoperability)
- [GitHub Issue #31230 — Deserialize third-party messages](https://github.com/symfony/symfony/issues/31230)
- [Dead Letter Queue in Symfony 6.3](https://medium.com/devwarlocks/dead-letter-queue-in-symfony-6-3-an-essential-guide-c95d7491851d)
