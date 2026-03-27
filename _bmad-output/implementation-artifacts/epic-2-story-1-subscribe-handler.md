# Story 2.1: Subscribe Command & Handler

**Epic**: 2 — Application Layer (Subscription Flow)
**Priority**: P0
**Estimated effort**: Medium
**Depends on**: Story 1.2

---

## Objective

Implement the subscribe use case: create or reactivate a subscription,
dispatch domain event for Mailchimp sync.

## Context

- Ref: PRD FR-2 (Suscribirse a una entidad)
- Ref: architecture.md Section 4.1 (Subscription Flow)
- Subscribe is synchronous for the response, async for Mailchimp sync

## Tasks

- [ ] `src/Application/Command/SubscribeCommand.php` — final readonly: userId, email, entityType (EntityType), entityId
- [ ] `src/Application/Command/SubscribeHandler.php`:
  - Inject: SubscriptionRepositoryInterface, MessageBusInterface
  - Logic:
    1. `findByUserAndEntity(userId, entityType, entityId)`
    2. If exists + active → return (idempotent, no side effects)
    3. If exists + inactive → `reactivate()`, save, dispatch SubscriptionCreatedEvent
    4. If not exists → `Subscription::create()`, save, dispatch SubscriptionCreatedEvent
  - Attribute: NOT `#[AsMessageHandler]` — this is called synchronously from controller
- [ ] `src/Application/DTO/SubscribeRequest.php`:
  - Fields: entityType (string, validated), entityId (string, not blank)
  - Symfony Validator constraints: `#[Assert\NotBlank]`, `#[Assert\Choice(['journalist','tag','section'])]`

### Unit Tests

- [ ] `tests/Application/Command/SubscribeHandlerTest.php`:
  - test creates new subscription when none exists
  - test reactivates inactive subscription
  - test is idempotent when subscription already active (no save, no event)
  - test dispatches SubscriptionCreatedEvent on new subscription
  - test dispatches SubscriptionCreatedEvent on reactivation

## Acceptance Criteria

- PRD AC-2.1: Creates new subscription with status active ✓
- PRD AC-2.2: Idempotent on already active ✓
- PRD AC-2.3: Reactivates inactive ✓
- PRD AC-2.6: Dispatches SubscriptionCreatedEvent ✓
- Handler is NOT a Messenger handler (sync call from controller)
- All tests pass with mocked repository and message bus
