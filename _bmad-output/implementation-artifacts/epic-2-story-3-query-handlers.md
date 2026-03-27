# Story 2.3: Query Handlers (Status + List)

**Epic**: 2 — Application Layer (Subscription Flow)
**Priority**: P0
**Estimated effort**: Small
**Depends on**: Story 1.2

---

## Objective

Implement the read queries: subscription status check (FR-1) and user
subscriptions list (FR-4). Pure reads, no side effects.

## Context

- Ref: PRD FR-1 (Consultar estado), FR-4 (Listar suscripciones)
- GET status must be < 50ms p95 (NFR-1) — single indexed query
- These are synchronous, called directly from controller

## Tasks

### GetSubscriptionStatus

- [ ] `src/Application/Query/GetSubscriptionStatusQuery.php` — final readonly: userId, entityType (EntityType), entityId
- [ ] `src/Application/Query/GetSubscriptionStatusHandler.php`:
  - Inject: SubscriptionRepositoryInterface
  - Logic: findByUserAndEntity → return `true` if found and active, `false` otherwise
  - Pure read, no events, no side effects

### GetUserSubscriptions

- [ ] `src/Application/Query/GetUserSubscriptionsQuery.php` — final readonly: userId
- [ ] `src/Application/Query/GetUserSubscriptionsHandler.php`:
  - Inject: SubscriptionRepositoryInterface
  - Logic: findActiveByUserId → map to array of {entity_type, entity_id}
  - Return empty array if none found

### Unit Tests

- [ ] `tests/Application/Query/GetSubscriptionStatusHandlerTest.php`:
  - test returns true when active subscription exists
  - test returns false when subscription not found
  - test returns false when subscription is inactive

- [ ] `tests/Application/Query/GetUserSubscriptionsHandlerTest.php`:
  - test returns list of active subscriptions
  - test returns empty array when no subscriptions

## Acceptance Criteria

- PRD AC-1.1, AC-1.2: Correct boolean status ✓
- PRD AC-4.1, AC-4.2, AC-4.3: Active subscriptions with correct fields ✓
- No side effects on read queries
- All tests pass
