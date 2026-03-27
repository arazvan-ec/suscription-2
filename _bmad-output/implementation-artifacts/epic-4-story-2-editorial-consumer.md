# Story 4.2: Editorial Published Consumer

**Epic**: 4 — Async Flows
**Priority**: P1
**Estimated effort**: Large
**Depends on**: Story 1.2, 3.2

---

## Objective

Consume `editorial.published` events from Jarvis CMS via RabbitMQ, verify
entity flags, check for followers, and create campaigns when appropriate.

## Context

- Ref: PRD FR-6 (Consumo de evento editorial.published)
- Ref: architecture.md Section 4.2 (Notification Flow)
- Ref: architecture.md ADR-5 (Custom Serializer)
- Ref: research-technical-patterns.md Topic 3 (External events)
- 4000 events/day — most will be discarded (no followers). Fast-path discard is critical.

## Tasks

### Port (Application Layer)

- [ ] `src/Application/Port/EntityFlagCheckerInterface.php`:
  - `getEnabledEntities(EditorialPublishedEvent $event): array`
  - Returns array of `['type' => EntityType, 'id' => string]` for entities with "follow" flag enabled

### Custom Serializer

- [ ] `src/Infrastructure/Messenger/Serializer/JarvisEventSerializer.php`:
  - Implements `Symfony\Component\Messenger\Transport\Serialization\SerializerInterface`
  - `decode()`: JSON decode raw body, map to EditorialPublishedEvent
    - Use null coalescing for optional fields: `$body['journalist_id'] ?? null`
    - Ignore unknown fields (schema evolution resilience)
    - Throw UnrecoverableMessageHandlingException for unparseable JSON
  - `encode()`: JSON encode for outbound (minimal, consume-only use case)

### Infrastructure

- [ ] `src/Infrastructure/Http/EntityFlagChecker.php` (implements EntityFlagCheckerInterface):
  - Constructor: HttpClientInterface, journalist/tag/section service URLs, LoggerInterface
  - Calls each service to check if entity has "follow" flag enabled
  - Timeout: 2s per service call
  - If a service is down, skip that entity type (graceful degradation)
  - Returns filtered list of enabled entities

- [ ] `src/Infrastructure/Messenger/EditorialPublishedHandler.php`:
  - `#[AsMessageHandler(fromTransport: 'jarvis_events')]`
  - Logic (fast-path discard):
    1. Build entity list from event (journalist, tags, section)
    2. Call EntityFlagChecker → get enabled entities
    3. If empty → discard, log info
    4. Call `hasActiveSubscriptionsForAny(entities)` → fast check (LIMIT 1)
    5. If no followers → discard, log info
    6. Create Campaign::create(type, editorialId, entities, scheduledAt = NOW + 5min)
    7. Save campaign, log campaign_id + entity_count

### Unit Tests

- [ ] `tests/Infrastructure/Messenger/EditorialPublishedHandlerTest.php`:
  - test creates campaign when entities have followers
  - test discards when no followers (fast-path)
  - test discards when no enabled entities
  - test discards when event has no entities at all (no journalist, no tags, no section)

- [ ] `tests/Infrastructure/Messenger/Serializer/JarvisEventSerializerTest.php`:
  - test decodes valid JSON to EditorialPublishedEvent
  - test handles missing optional fields with defaults
  - test throws on unparseable JSON

## Acceptance Criteria

- PRD AC-6.1: Consumes editorial.published from RabbitMQ ✓
- PRD AC-6.2: Extracts entities from event ✓
- PRD AC-6.3: Checks flags via external services ✓
- PRD AC-6.4: Fast-path follower check (LIMIT 1) ✓
- PRD AC-6.5: Discards when no followers ✓
- PRD AC-6.6: Creates Campaign with scheduled_at = NOW + 5min ✓
- PRD AC-6.8: Failed messages go to dead-letter (transport retry config) ✓
- Custom serializer handles schema evolution ✓
- All tests pass
