# Story 3.2: Subscription Controller & Doctrine Repositories

**Epic**: 3 — Infrastructure Layer (API)
**Priority**: P0
**Estimated effort**: Large
**Depends on**: Story 2.1, 2.2, 2.3, 3.1

---

## Objective

Implement the REST API controller for subscription management, the Doctrine
repository implementations, and the database migration. This is the full
wiring that connects HTTP to Domain.

## Context

- Ref: PRD FR-1 to FR-4 (all subscription endpoints)
- Ref: architecture.md Section 4.1 (Subscription Flow)
- Ref: architecture.md Section 3 (Data Model)

## Tasks

### Doctrine Repositories

- [ ] `src/Infrastructure/Persistence/DoctrineSubscriptionRepository.php`:
  - Extends ServiceEntityRepository, implements SubscriptionRepositoryInterface
  - `save()`: persist + flush
  - `findByUserAndEntity()`: findOneBy
  - `findActiveByUserId()`: findBy with status=active
  - `findActiveByEntities(array)`: QueryBuilder with OR conditions per entity
  - `hasActiveSubscriptionsForAny(array)`: SELECT 1 ... LIMIT 1 (fast-path for editorial consumer)

- [ ] `src/Infrastructure/Persistence/DoctrineCampaignRepository.php`:
  - Extends ServiceEntityRepository, implements CampaignRepositoryInterface
  - `save()`: persist + flush
  - `find()`: parent find
  - `findReadyToProcess()`: WHERE status=pending AND scheduled_at <= :now, ORDER BY scheduled_at ASC

### Database Migration

- [ ] `migrations/Version20260327000001.php`:
  - CREATE TABLE subscriptions (as per architecture.md Section 3.1)
  - CREATE TABLE campaigns (as per architecture.md Section 3.2)
  - CREATE TABLE messenger_messages (for failed transport)
  - All indexes and constraints

### Controller

- [ ] `src/Infrastructure/Controller/SubscriptionController.php`:
  - `#[Route('/api/v1')]` prefix
  - Inject: JwtAuthenticator, SubscribeHandler, UnsubscribeHandler, GetSubscriptionStatusHandler, GetUserSubscriptionsHandler

  - `GET /subscriptions/{entityType}/{entityId}`:
    - Authenticate → validate entityType → call StatusHandler → return {subscribed: bool}
    - Headers: Cache-Control: private, no-store

  - `POST /subscriptions`:
    - Authenticate → validate body with `#[MapRequestPayload] SubscribeRequest` → call SubscribeHandler
    - Return {subscribed: true}

  - `DELETE /subscriptions/{entityType}/{entityId}`:
    - Authenticate → validate entityType → call UnsubscribeHandler
    - Return {subscribed: false}

  - `GET /subscriptions`:
    - Authenticate → call ListHandler → return {subscriptions: [...]}
    - Headers: Cache-Control: private, no-store

### Health Controller

- [ ] `src/Infrastructure/Controller/HealthController.php`:
  - `GET /health` — No auth, returns {status: ok, service: enbandeja-service}

### Service Wiring

- [ ] Update `config/packages/app.yaml`:
  - Bind SubscriptionRepositoryInterface → DoctrineSubscriptionRepository
  - Bind CampaignRepositoryInterface → DoctrineCampaignRepository

### Unit Tests

- [ ] `tests/Infrastructure/Controller/SubscriptionControllerTest.php`:
  - test GET status returns subscribed true/false
  - test POST subscribe returns 200
  - test DELETE unsubscribe returns 200
  - test GET list returns subscriptions array
  - test 401 for unauthenticated requests
  - test 400 for invalid entityType
  - test 422 for invalid POST body

## Acceptance Criteria

- All 4 subscription endpoints work (FR-1 to FR-4) ✓
- Health endpoint works (FR-8) ✓
- RFC 7807 errors for 400, 401, 422 ✓
- Cache-Control: private, no-store on GET endpoints ✓
- Doctrine repositories implement domain interfaces ✓
- Migration creates both tables with correct indexes ✓
- All tests pass
