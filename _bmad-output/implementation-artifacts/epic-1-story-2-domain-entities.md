# Story 1.2: Domain Layer — Entities, Value Objects, Events

**Epic**: 1 — Foundation
**Priority**: P0 (blocks Application layer)
**Estimated effort**: Medium

---

## Objective

Implement the complete Domain layer: entities with rich behavior, value objects
(enums), domain events, and repository interfaces.

## Context

- Ref: architecture.md Section 3 (Data Model)
- Ref: architecture.md ADR-2 (Soft Delete), ADR-3 (Enum Entity Types)
- Ref: PRD Section 4 (Data Model Summary)
- Convention: private constructor + static `create()` factory
- Convention: \DateTimeImmutable for all timestamps, UUID v7 for IDs

## Tasks

### Value Objects

- [ ] `src/Domain/ValueObject/EntityType.php` — Backed enum: journalist, tag, section
- [ ] `src/Domain/ValueObject/SubscriptionStatus.php` — Backed enum: active, inactive
- [ ] `src/Domain/ValueObject/CampaignStatus.php` — Backed enum: pending, processing, sent, failed
- [ ] `src/Domain/ValueObject/CampaignType.php` — Backed enum: editorial_notification

### Entities

- [ ] `src/Domain/Entity/Subscription.php`:
  - Fields: id (UUID v7), userId, email, entityType (EntityType enum), entityId, status (SubscriptionStatus enum), createdAt, updatedAt
  - Private constructor + `create()` factory
  - Methods: `deactivate()`, `reactivate()`, `isActive()`
  - Doctrine ORM attributes: Table "subscriptions", UniqueConstraint(user_id, entity_type, entity_id), Index(entity_type, entity_id, status), Index(user_id, status)
  - `entity_type` column as `enumType: EntityType::class` (stored as VARCHAR via backed enum)

- [ ] `src/Domain/Entity/Campaign.php`:
  - Fields: id (UUID v7), type (CampaignType), status (CampaignStatus), scheduledAt, audienceCriteria (JSON array), editorialId, createdAt
  - Private constructor + `create()` factory
  - Methods: `markAsProcessing()`, `markAsSent()`, `markAsFailed()`, `isPending()`, `isReadyToProcess()`
  - Doctrine ORM attributes: Table "campaigns", Index(status, scheduled_at)

### Domain Events

- [ ] `src/Domain/Event/SubscriptionCreatedEvent.php` — final readonly: userId, email, entityType, entityId, occurredAt
- [ ] `src/Domain/Event/SubscriptionDeletedEvent.php` — final readonly: userId, email, entityType, entityId, occurredAt
- [ ] `src/Domain/Event/EditorialPublishedEvent.php` — final readonly: editorialId, title, journalistId(?), tagIds[], sectionId(?), occurredAt

### Repository Interfaces

- [ ] `src/Domain/Repository/SubscriptionRepositoryInterface.php`:
  - `save(Subscription)`, `findByUserAndEntity(userId, EntityType, entityId)`, `findActiveByUserId(userId)`, `findActiveByEntities(array)`, `hasActiveSubscriptionsForAny(array)`

- [ ] `src/Domain/Repository/CampaignRepositoryInterface.php`:
  - `save(Campaign)`, `find(string $id)`, `findReadyToProcess(\DateTimeImmutable $now)`

### Unit Tests

- [ ] `tests/Domain/Entity/SubscriptionTest.php`:
  - test create() sets active status and UUID v7
  - test deactivate() sets inactive, updates updatedAt
  - test reactivate() sets active
  - test isActive() returns correct bool

- [ ] `tests/Domain/Entity/CampaignTest.php`:
  - test create() sets pending status
  - test status transitions: pending → processing → sent
  - test status transitions: pending → processing → failed
  - test isReadyToProcess() with past and future scheduledAt

## Acceptance Criteria

- All entities use private constructor + static create() factory
- Soft delete only (no hard delete methods on Subscription)
- EntityType is a PHP backed enum + VARCHAR in DB (ADR-3)
- All domain events are final readonly
- All tests pass
- Domain layer has ZERO imports from Application or Infrastructure
