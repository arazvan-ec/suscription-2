# Project Context: enBandeja — Subscription & Notification Service

## Overview

enBandeja is a microservice that manages user subscriptions (follow/unfollow)
to editorial entities and sends notifications when new content is published.
It is part of El Confidencial's microservice ecosystem.

## Service Identity

- **Name**: enBandeja-service (suscription-2)
- **Type**: Symfony 8 API microservice
- **Domain**: Subscriptions + Audiences + Campaigns
- **Auth**: JWT via web-token/jwt-framework (header X-Auth-Token or cookie accessToken)

## Architecture

Three-layer DDD structure:
- `src/Domain/` — Entities, Value Objects, Repository Interfaces, Domain Events
- `src/Application/` — Use Cases (Command/Query handlers), DTOs
- `src/Infrastructure/` — Controllers, Doctrine repos, Messenger handlers, HTTP clients

## Core Entities

### Subscription
- Fields: id (UUID v7), user_id, email, entity_type, entity_id, status (active/inactive), created_at, updated_at
- Unique constraint: (user_id, entity_type, entity_id)
- Soft delete via status field
- Entity types: `journalist`, `tag`, `section`

### Campaign
- Fields: id (UUID v7), type, status (pending/sent/failed), scheduled_at, audience_criteria (JSON), editorial_id, created_at
- Created when editorial.published event arrives and entities have followers
- Worker processes campaigns when scheduled_at <= NOW()

## Flows

### Subscription Flow (user-facing)
1. CDN serves cached page with placeholder button
2. JS (delorian-statics) reads accessToken cookie, calls GET /subscriptions/{entityType}/{entityId}
3. Button renders "Seguir" or "Dejar de seguir"
4. Click "Seguir" → POST /subscriptions → status: active
5. Click "Dejar de seguir" → DELETE /subscriptions/{entityType}/{entityId} → status: inactive (soft delete)
6. Async: sync with Mailchimp Marketing API (add/remove from audience)

### Notification Flow (editorial-triggered)
1. Jarvis CMS publishes editorial → editorial.published event → RabbitMQ
2. enBandeja consumes event, checks entity flags (journalist-svc, tag-svc, section-svc)
3. If entities have active followers → create Campaign
4. Worker resolves fresh subscriber data, dispatches to notifier-service
5. notifier-service sends via Mailchimp Transactional

## Ecosystem Dependencies

| Service | Role | Communication |
|---------|------|---------------|
| Jarvis (CMS/BFF) | Publishes editorial events | RabbitMQ (editorial.published) |
| journalist-service | Journalist metadata & flags | REST API |
| tag-service | Tag metadata & flags | REST API |
| section-service | Section metadata & flags | REST API |
| notifier-service | Email delivery only | RabbitMQ (send.notification) |
| delorian-statics | Frontend JS, button rendering | Direct HTTP to enBandeja |
| Mailchimp Marketing | Audience sync | REST API (async) |
| Mailchimp Transactional | Email sending (via notifier) | REST API |

## Technical Conventions

- REST APIs follow RFC 7807 for error responses
- JWT auth, header X-Auth-Token
- CDN: Transparent Edge, Vary: X-User-Level for public endpoints
- Personalized endpoints: Cache-Control: private, no-store
- Async processing: Symfony Messenger + RabbitMQ
- Retry: exponential backoff (1s, 2s, 4s), max 3 retries
- Database: PostgreSQL, Doctrine ORM, UUID v7 primary keys
- Naming: Commands (VerbNounCommand), Queries (GetNounQuery), Handlers (CommandHandler)

## Non-Functional Requirements

- Subscription check (GET) must respond < 100ms (user-facing, synchronous)
- Subscribe/unsubscribe must respond < 200ms (Mailchimp sync is async)
- Campaign processing is async, target < 5 min from editorial publish to notification dispatch
- Must handle 10K+ subscribers per entity without performance degradation
