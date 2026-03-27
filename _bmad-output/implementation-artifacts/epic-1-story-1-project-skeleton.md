# Story 1.1: Project Skeleton & Configuration

**Epic**: 1 — Foundation
**Priority**: P0 (blocks everything)
**Estimated effort**: Small

---

## Objective

Set up the Symfony 8 project skeleton with all required dependencies, configuration
files, and the three-layer DDD directory structure.

## Context

- Ref: architecture.md Section 2 (Internal Architecture)
- Ref: architecture.md Section 8 (Infrastructure)
- This is a greenfield Symfony 8 API microservice

## Tasks

- [ ] Create `composer.json` with all dependencies:
  - symfony/framework-bundle 8.0.*
  - doctrine/orm, doctrine/doctrine-bundle, doctrine/doctrine-migrations-bundle
  - symfony/messenger, symfony/amqp-messenger
  - symfony/scheduler (for campaign processing — ADR-4)
  - symfony/uid (UUID v7)
  - symfony/validator, symfony/serializer, symfony/property-access
  - symfony/lock (for Scheduler lock — ADR-4)
  - web-token/jwt-framework ^4.0
  - nelmio/cors-bundle
  - phpunit/phpunit (dev)
  - symfony/maker-bundle (dev)
- [ ] Create `src/Kernel.php`
- [ ] Create `public/index.php` and `bin/console`
- [ ] Create directory structure:
  - `src/Domain/{Entity,ValueObject,Event,Repository}`
  - `src/Application/{Command,Query,DTO,Port}`
  - `src/Infrastructure/{Controller,Persistence,Messenger,Messenger/Serializer,Http,Security,Scheduler,Console}`
- [ ] Create config files:
  - `config/bundles.php`
  - `config/services.yaml` (autowire, autoconfigure, exclude Domain entities/events/VOs)
  - `config/routes.yaml` (attribute routing from Infrastructure/Controller)
  - `config/packages/framework.yaml` (serializer, validator, uid v7)
  - `config/packages/doctrine.yaml` (PostgreSQL, UUID type, attribute mapping from Domain/Entity)
  - `config/packages/doctrine_migrations.yaml`
  - `config/packages/messenger.yaml` (4 transports: async, jarvis_events, mailchimp_sync, failed)
  - `config/packages/nelmio_cors.yaml` (allow X-Auth-Token header)
  - `config/packages/lock.yaml` (LOCK_DSN for Scheduler)
- [ ] Create `.env` with all env vars from architecture.md Section 8.2
- [ ] Create `.env.test` with sync transport and test values
- [ ] Create `docker-compose.yaml` (PostgreSQL 16, RabbitMQ 3 with management)
- [ ] Create `.gitignore` (vendor/, var/, .env.local, phpunit.xml)
- [ ] Create `phpunit.xml.dist` with 3 test suites: Domain, Application, Infrastructure

## Acceptance Criteria

- `composer validate` passes
- Directory structure matches architecture.md Section 2
- All 9 env vars from architecture.md Section 8.2 are present in .env
- 4 Messenger transports configured: async, jarvis_events, mailchimp_sync, failed
- Symfony Scheduler lock configured

## Notes

- Do NOT install dependencies (no `composer install`) — just create the files
- The jarvis_events transport must use a custom serializer path: `App\Infrastructure\Messenger\Serializer\JarvisEventSerializer` (ADR-5)
- The mailchimp_sync transport is separate from async (ADR-6)
