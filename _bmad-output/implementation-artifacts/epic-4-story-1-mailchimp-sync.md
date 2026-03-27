# Story 4.1: Mailchimp Audience Sync

**Epic**: 4 — Async Flows
**Priority**: P1
**Estimated effort**: Medium
**Depends on**: Story 1.2, 3.2

---

## Objective

Implement async Mailchimp Marketing API audience synchronization triggered by
subscription domain events. Add/remove members and manage tags.

## Context

- Ref: PRD FR-5 (Sincronización de audiencias con Mailchimp)
- Ref: architecture.md ADR-6 (Transport dedicado), ADR-7 (Hybrid sync)
- Ref: research-technical-patterns.md Topic 1 (Mailchimp patterns)
- Transport: mailchimp_sync (dedicated, prefetch limited)

## Tasks

### Port (Application Layer)

- [ ] `src/Application/Port/MailchimpClientInterface.php`:
  - `addToAudience(string $email, array $tags): void`
  - `removeFromAudience(string $email): void`

### Commands

- [ ] `src/Application/Command/SyncMailchimpCommand.php`:
  - final readonly: email, action (subscribe/unsubscribe), tags (array)
  - Constants: ACTION_SUBSCRIBE, ACTION_UNSUBSCRIBE

### Infrastructure

- [ ] `src/Infrastructure/Http/MailchimpClient.php` (implements MailchimpClientInterface):
  - Constructor: HttpClientInterface, mailchimpApiKey, mailchimpListId, mailchimpDataCenter, LoggerInterface
  - `addToAudience()`: PUT /lists/{id}/members/{hash} with status_if_new=subscribed, then POST .../tags
  - `removeFromAudience()`: PATCH /lists/{id}/members/{hash} with status=unsubscribed
  - subscriber_hash = md5(strtolower($email))
  - Tags format: `{entity_type}:{entity_id}`
  - Log success/failure, but do NOT throw on Mailchimp errors (best-effort sync)

- [ ] `src/Infrastructure/Messenger/SyncMailchimpHandler.php`:
  - `#[AsMessageHandler(fromTransport: 'mailchimp_sync')]`
  - Match action: subscribe → addToAudience, unsubscribe → removeFromAudience
  - Log entry + exit

- [ ] `src/Infrastructure/Messenger/SubscriptionEventHandler.php`:
  - Two handler methods with `#[AsMessageHandler]` on each:
    - `onSubscriptionCreated(SubscriptionCreatedEvent)` → dispatch SyncMailchimpCommand(subscribe)
    - `onSubscriptionDeleted(SubscriptionDeletedEvent)` → dispatch SyncMailchimpCommand(unsubscribe)
  - Tags: `["{entityType.value}:{entityId}"]`

### Messenger Routing

- [ ] Update `config/packages/messenger.yaml`:
  - Route SyncMailchimpCommand → mailchimp_sync transport

### Unit Tests

- [ ] `tests/Infrastructure/Messenger/SyncMailchimpHandlerTest.php`:
  - test calls addToAudience for subscribe action
  - test calls removeFromAudience for unsubscribe action

- [ ] `tests/Infrastructure/Messenger/SubscriptionEventHandlerTest.php`:
  - test dispatches SyncMailchimpCommand on SubscriptionCreatedEvent
  - test dispatches SyncMailchimpCommand on SubscriptionDeletedEvent

## Acceptance Criteria

- PRD AC-5.1: Subscribe → adds to Mailchimp audience with tags ✓
- PRD AC-5.2: Unsubscribe → marks as unsubscribed in Mailchimp ✓
- PRD AC-5.3: Sync is async (dedicated transport) ✓
- PRD AC-5.4: Retries on failure (transport retry strategy) ✓
- PRD AC-5.5: Tags use format entity_type:entity_id ✓
- MailchimpClient errors are logged but do NOT propagate (best-effort)
