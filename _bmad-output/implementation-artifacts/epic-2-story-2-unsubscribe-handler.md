# Story 2.2: Unsubscribe Command & Handler

**Epic**: 2 — Application Layer (Subscription Flow)
**Priority**: P0
**Estimated effort**: Small
**Depends on**: Story 1.2

---

## Objective

Implement the unsubscribe (soft delete) use case: deactivate a subscription,
dispatch domain event for Mailchimp sync.

## Context

- Ref: PRD FR-3 (Cancelar suscripción)
- Ref: architecture.md ADR-2 (Soft Delete via Status)
- Soft delete ONLY — never hard delete

## Tasks

- [ ] `src/Application/Command/UnsubscribeCommand.php` — final readonly: userId, entityType (EntityType), entityId
- [ ] `src/Application/Command/UnsubscribeHandler.php`:
  - Inject: SubscriptionRepositoryInterface, MessageBusInterface
  - Logic:
    1. `findByUserAndEntity(userId, entityType, entityId)`
    2. If not found or already inactive → return (idempotent)
    3. If active → `deactivate()`, save, dispatch SubscriptionDeletedEvent
  - Called synchronously from controller (not a Messenger handler)

### Unit Tests

- [ ] `tests/Application/Command/UnsubscribeHandlerTest.php`:
  - test deactivates active subscription
  - test dispatches SubscriptionDeletedEvent
  - test idempotent when subscription not found
  - test idempotent when subscription already inactive

## Acceptance Criteria

- PRD AC-3.1: Changes status to inactive (soft delete) ✓
- PRD AC-3.2: Idempotent on missing/inactive ✓
- PRD AC-3.3: Dispatches SubscriptionDeletedEvent ✓
- All tests pass
