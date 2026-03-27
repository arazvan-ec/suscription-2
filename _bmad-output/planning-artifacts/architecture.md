# Architecture: enBandeja-service

**Architect**: Winston (BMAD Architect)
**Date**: 2026-03-27
**Version**: 1.0
**Status**: Draft
**Inputs**: PRD v1.0, brainstorming-report, research-technical-patterns

---

## 1. System Overview

```
                           ┌─────────────────────┐
                           │  Transparent Edge    │
                           │  (CDN)               │
                           │  HTML + placeholder  │
                           └────────┬────────────┘
                                    │ cached page
                                    ▼
                           ┌─────────────────────┐
                           │  delorian-statics    │
                           │  (Browser JS)        │
                           │  cookie: accessToken │
                           └────────┬────────────┘
                                    │ REST (JWT)
                                    ▼
┌──────────────┐          ┌─────────────────────┐         ┌──────────────────┐
│ Jarvis CMS   │──AMQP──▶│  enBandeja-service   │──AMQP──▶│ notifier-service │
│              │          │                     │         │ (symfony-notifier)│
│ editorial    │          │ ┌─────────────────┐ │         └──────────────────┘
│ .published   │          │ │ Subscriptions   │ │
└──────────────┘          │ │ Audiences       │ │         ┌──────────────────┐
                          │ │ Campaigns       │ │──HTTP──▶│ Mailchimp        │
┌──────────────┐          │ └─────────────────┘ │         │ Marketing API    │
│ journalist   │◀──HTTP───│                     │         └──────────────────┘
│ tag-svc      │          │      PostgreSQL     │
│ section-svc  │          │      RabbitMQ       │
└──────────────┘          └─────────────────────┘
```

### Boundaries

| Boundary | enBandeja owns | External system owns |
|----------|---------------|---------------------|
| Suscripciones | CRUD, storage, status | — |
| Audiencias Mailchimp | Sync (add/remove members) | Audience storage, segmentation |
| Campañas | Creation, scheduling, dispatch | — |
| Envío de email | Dispatch (destinatarios + contenido) | Delivery (notifier-service) |
| Flags de entidad | Consulta | Gestión (journalist/tag/section-svc) |
| Autenticación | Validación JWT | Emisión JWT (auth service) |
| Rendering botón | — | delorian-statics (JS) |
| Cacheo página | Response headers | Transparent Edge (CDN) |

---

## 2. Internal Architecture

Three-layer DDD con separación estricta:

```
src/
├── Domain/                          # Cero dependencias externas
│   ├── Entity/                      # Subscription, Campaign
│   ├── ValueObject/                 # EntityType, SubscriptionStatus, CampaignStatus
│   ├── Event/                       # SubscriptionCreated, SubscriptionDeleted, EditorialPublished
│   └── Repository/                  # Interfaces (ports)
│
├── Application/                     # Orquestación, usa Domain, no conoce Infrastructure
│   ├── Command/                     # SubscribeCommand + Handler, UnsubscribeCommand + Handler
│   ├── Query/                       # GetSubscriptionStatusQuery + Handler, GetUserSubscriptionsQuery + Handler
│   ├── DTO/                         # SubscribeRequest (input validation)
│   └── Port/                        # MailchimpClientInterface, EntityFlagCheckerInterface
│
└── Infrastructure/                  # Implementaciones concretas
    ├── Controller/                  # SubscriptionController, HealthController
    ├── Persistence/                 # DoctrineSubscriptionRepository, DoctrineCampaignRepository
    ├── Messenger/                   # Handlers async: Editorial, Campaign, Mailchimp sync
    │   └── Serializer/              # JarvisEventSerializer (custom, para eventos externos)
    ├── Http/                        # ApiProblemResponse, MailchimpClient, EntityFlagChecker
    ├── Security/                    # JwtAuthenticator, JwtTokenDecoder, JwtTokenExtractor
    ├── Scheduler/                   # CampaignScheduleProvider (Symfony Scheduler)
    └── Console/                     # ProcessPendingCampaignsCommand (fallback cron)
```

### Dependency Rule

```
Controller → Application (Command/Query) → Domain (Entity/Repository interface)
                                                    ↑
Infrastructure (Doctrine, HTTP, Messenger) ─────────┘ (implements interfaces)
```

Domain **nunca** importa de Application ni Infrastructure.
Application **nunca** importa de Infrastructure.

---

## 3. Data Model

### 3.1 Subscription

```sql
CREATE TABLE subscriptions (
    id              UUID PRIMARY KEY,           -- UUID v7 (time-sortable)
    user_id         VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    entity_type     VARCHAR(50)  NOT NULL,      -- VARCHAR, no PG enum (extensible)
    entity_id       VARCHAR(255) NOT NULL,
    status          VARCHAR(20)  NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL,
    updated_at      TIMESTAMP    NOT NULL,

    CONSTRAINT uniq_user_entity UNIQUE (user_id, entity_type, entity_id)
);

CREATE INDEX idx_entity_status ON subscriptions (entity_type, entity_id, status);
CREATE INDEX idx_user_status   ON subscriptions (user_id, status);
```

**Notas**:
- `entity_type` es VARCHAR(50) y no un ENUM de PostgreSQL → añadir tipos sin migración
- `status` siempre `active` o `inactive` → soft delete, nunca `DELETE FROM`
- Unique constraint previene suscripciones duplicadas → upsert logic en handler
- `idx_entity_status` para el fast-path discard del consumer editorial (query más frecuente: "¿hay algún follower para esta entidad?")

### 3.2 Campaign

```sql
CREATE TABLE campaigns (
    id                UUID PRIMARY KEY,
    type              VARCHAR(50)  NOT NULL,
    status            VARCHAR(20)  NOT NULL DEFAULT 'pending',
    scheduled_at      TIMESTAMP    NOT NULL,
    audience_criteria JSONB        NOT NULL,
    editorial_id      VARCHAR(255) NOT NULL,
    created_at        TIMESTAMP    NOT NULL
);

CREATE INDEX idx_status_scheduled ON campaigns (status, scheduled_at);
```

**Notas**:
- `audience_criteria` es JSONB → flexible para diferentes combinaciones de entidades
- `idx_status_scheduled` cubre la query del Scheduler: `WHERE status = 'pending' AND scheduled_at <= NOW()`
- Status transitions: `pending → processing → sent | failed`

---

## 4. Component Design

### 4.1 Subscription Flow (síncrono, user-facing)

```
delorian-statics ──JWT──▶ SubscriptionController
                              │
                    ┌─────────┼──────────┐
                    ▼         ▼          ▼
                GET status  POST sub  DELETE unsub
                    │         │          │
                    ▼         ▼          ▼
              QueryHandler  CmdHandler  CmdHandler
                    │         │          │
                    ▼         ▼          ▼
              SubscriptionRepository (Doctrine)
                              │
                              ▼
                    Dispatch async events
                    ┌─────────────────┐
                    │ SubscriptionCreatedEvent │──▶ SyncMailchimpHandler
                    │ SubscriptionDeletedEvent │──▶ SyncMailchimpHandler
                    └─────────────────┘
```

**Decisiones clave**:
- GET es síncrono y directo a DB (< 50ms target)
- POST/DELETE son síncronos para la respuesta, pero el sync Mailchimp es async
- Idempotencia: POST sobre suscripción activa → 200 OK sin side effects
- Reactivación: POST sobre suscripción inactiva → reactivate() + 200 OK

### 4.2 Notification Flow (async, editorial-triggered)

```
Jarvis ──AMQP──▶ [jarvis_events transport]
                       │
                       ▼ JarvisEventSerializer (custom)
                       │
              EditorialPublishedHandler
                       │
                ┌──────┴──────┐
                ▼             ▼
        EntityFlagChecker   hasActiveSubscriptionsForAny()
        (HTTP → svc's)      (DB, LIMIT 1, fast-path)
                │             │
                └──────┬──────┘
                       │
                  ¿Followers? ──No──▶ Discard (log info)
                       │
                      Yes
                       ▼
                Campaign::create(scheduled_at = NOW + 5min)
                       │
                       ▼
                  Save to DB
```

**Fast-path discard**: La mayoría de los 4000 editoriales/día NO tendrán seguidores.
El `hasActiveSubscriptionsForAny()` usa `SELECT 1 ... LIMIT 1` para abortar pronto.

### 4.3 Campaign Processing (scheduled, async)

```
Symfony Scheduler (cada 60s)
        │
        ▼ ProcessPendingCampaignsMessage
        │
        ▼ Lock: campaign-scheduler (1 pod a la vez)
        │
        ▼
SELECT campaigns WHERE status='pending' AND scheduled_at <= NOW()
  FOR UPDATE SKIP LOCKED
        │
        ▼ Para cada campaign:
        │
        ├─▶ status = 'processing'
        ├─▶ findActiveByEntities(audience_criteria) → subscribers frescos
        ├─▶ Deduplicate by email
        ├─▶ Dispatch SendNotificationCommand → notifier-service (AMQP)
        └─▶ status = 'sent' (o 'failed' si error)
```

**Doble protección**:
1. Scheduler lock → solo 1 pod dispara el schedule
2. `SELECT ... FOR UPDATE SKIP LOCKED` → cada campaign la reclama 1 handler
3. Status transition atómica → pending → processing → sent

### 4.4 Mailchimp Audience Sync (async)

```
SubscriptionCreated/DeletedEvent
        │
        ▼ (async transport)
SyncMailchimpHandler
        │
        ├─▶ PUT /lists/{id}/members/{hash}  (upsert member)
        └─▶ POST /lists/{id}/members/{hash}/tags  (manage tags)

        Failure:
        ├─▶ Retry 3x (1s, 2s, 4s backoff)
        ├─▶ Failed transport (Doctrine) after max retries
        └─▶ Circuit breaker if N consecutive failures
```

**Tags**: formato `{entity_type}:{entity_id}` (ej: `journalist:456`)
**Reconciliation**: comando `app:mailchimp:reconcile` para reparar drift

---

## 5. Messaging Architecture

### 5.1 Transports

```yaml
transports:
    # Internal async — commands and domain events
    async:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        options:
            exchange: { name: enbandeja, type: topic }
            queues:
                enbandeja_subscriptions:
                    binding_keys: ['subscription.#']
        retry_strategy:
            max_retries: 3
            delay: 1000
            multiplier: 2
            max_delay: 30000

    # External — events from Jarvis CMS
    jarvis_events:
        dsn: '%env(JARVIS_TRANSPORT_DSN)%'
        serializer: App\Infrastructure\Messenger\Serializer\JarvisEventSerializer
        options:
            exchange: { name: jarvis, type: topic }
            queues:
                enbandeja_editorial:
                    binding_keys: ['editorial.published']
        retry_strategy:
            max_retries: 3
            delay: 1000
            multiplier: 2
            max_delay: 30000

    # Mailchimp sync — dedicated, concurrency-limited
    mailchimp_sync:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        options:
            exchange: { name: enbandeja, type: topic }
            queues:
                enbandeja_mailchimp:
                    binding_keys: ['mailchimp.#']
        retry_strategy:
            max_retries: 3
            delay: 2000
            multiplier: 2
            max_delay: 60000

    # Failed messages — Doctrine for inspection
    failed:
        dsn: 'doctrine://default?queue_name=failed'
```

### 5.2 Message Routing

| Message | Transport | Reason |
|---------|-----------|--------|
| SubscribeCommand | sync | User-facing, respuesta inmediata |
| UnsubscribeCommand | sync | User-facing, respuesta inmediata |
| SubscriptionCreatedEvent | async | Trigger Mailchimp sync |
| SubscriptionDeletedEvent | async | Trigger Mailchimp sync |
| SyncMailchimpCommand | mailchimp_sync | Dedicado, concurrency=5 |
| EditorialPublishedEvent | jarvis_events | Externo, custom serializer |
| ProcessCampaignCommand | async | Dispatch desde Scheduler |
| SendNotificationCommand | async | Dispatch a notifier-service |

### 5.3 Workers

```bash
# Worker principal: internal async + scheduler
messenger:consume async scheduler_campaigns -l 128 --time-limit=3600

# Worker Jarvis events: dedicado
messenger:consume jarvis_events -l 64 --time-limit=3600

# Worker Mailchimp sync: dedicado, prefetch limitado a 5
messenger:consume mailchimp_sync -l 64 --time-limit=3600 \
  --queues=enbandeja_mailchimp
```

3 workers separados para escalar independientemente.

---

## 6. Security

### 6.1 JWT Authentication

```
Request → JwtTokenExtractor → JwtTokenDecoder → AuthenticatedUser
           │                      │
           ├─ Header: X-Auth-Token│
           └─ Cookie: accessToken │
                                  ├─ Verify signature (HS256)
                                  ├─ Check expiration
                                  └─ Extract: sub (userId), email
```

- Validación local del JWT (no llama a servicio externo)
- Secreto compartido via env var `JWT_SECRET`
- Todos los endpoints `/api/v1/*` requieren auth
- `/health` excluido de auth

### 6.2 Data Isolation

- Un usuario SOLO puede ver/modificar sus propias suscripciones
- El userId se extrae del JWT, nunca del request body
- No existe endpoint admin en MVP (futuro: backoffice con roles)

---

## 7. Architecture Decision Records (ADRs)

### ADR-1: Three-Layer DDD Architecture

**Context**: Necesitamos separación entre dominio, lógica de aplicación e infraestructura.
**Decision**: Tres capas: Domain / Application / Infrastructure con dependency rule estricta.
**Rationale**: Patrón estándar en el ecosistema El Confidencial. Testabilidad: domain y application se testean con mocks. Infrastructure se testea con integración.
**Consequences**: Más archivos y boilerplate, pero clara separación de responsabilidades.

### ADR-2: Soft Delete via Status Field

**Context**: Las suscripciones se cancelan frecuentemente y pueden reactivarse.
**Decision**: Campo `status` (active/inactive), nunca `DELETE FROM`.
**Rationale**: Permite reactivación sin perder historial. GDPR traceability. Unique constraint funciona con ambos estados. Hard delete rompería la idempotencia del POST.
**Consequences**: Las queries deben siempre filtrar por `status = active`.

### ADR-3: Enum Cerrado para Entity Types (extensible por diseño)

**Context**: Actualmente 3 tipos (journalist, tag, section), futuro incierto.
**Decision**: PHP enum backed by string + VARCHAR(50) en DB (no PG enum).
**Rationale**: Type-safety en código, sin migración para nuevos tipos en DB. Añadir tipo = añadir caso al enum + deploy. VARCHAR en DB acepta cualquier string futuro.
**Consequences**: Añadir tipo requiere deploy, pero es una operación controlada y poco frecuente.

### ADR-4: Symfony Scheduler para Campaign Processing

**Context**: Necesitamos procesar campañas periódicamente con garantías exactly-once en multi-pod.
**Decision**: Symfony Scheduler component con `->stateful()` + `->lock()`, más `SELECT FOR UPDATE SKIP LOCKED` en DB.
**Rationale**: Integración nativa con Messenger. Doble protección: lock a nivel schedule + lock a nivel DB. Container-friendly (no cron externo). Ref: research-technical-patterns.md Topic 2.
**Alternative rejected**: Cron + console command (no container-friendly, lock manual).
**Consequences**: Requiere shared lock store (Redis o PostgreSQL advisory locks).

### ADR-5: Custom Serializer para Eventos de Jarvis

**Context**: Jarvis publica eventos sin formato envelope de Symfony Messenger.
**Decision**: Custom `SerializerInterface` configurado solo en el transport `jarvis_events`.
**Rationale**: Patrón estándar de Symfony. Full control sobre deserialización. Resiliente a evolución de schema (null coalescing, ignore unknown fields). Ref: research-technical-patterns.md Topic 3.
**Alternative rejected**: RabbitMQ Shovel (infraestructura adicional sin ventaja real).
**Consequences**: Hay que mantener el serializer sincronizado con cambios de schema de Jarvis.

### ADR-6: Transport Dedicado para Mailchimp Sync

**Context**: Mailchimp tiene límite de 10 conexiones concurrentes. El sync no debe interferir con el procesamiento principal.
**Decision**: Transport `mailchimp_sync` dedicado, worker separado con prefetch limitado.
**Rationale**: Aislamiento: si Mailchimp está lento/caído, no bloquea el procesamiento de editoriales ni suscripciones. Control de concurrencia via worker dedicado. Circuit breaker para pausar si Mailchimp falla. Ref: research-technical-patterns.md Topic 1.
**Alternative rejected**: Mismo transport async (riesgo de starvation si Mailchimp es lento).
**Consequences**: 3 workers en vez de 1, pero cada uno escala independientemente.

### ADR-7: Hybrid Mailchimp Sync (Individual + Batch)

**Context**: Subscribe/unsubscribe necesitan sync near-real-time. Reconciliación necesita bulk.
**Decision**: PUT individual para eventos real-time. POST /batches para reconciliación on-demand.
**Rationale**: Volumen de suscripciones es bajo-medio (no 4000/día — esos son editoriales). PUT individual bajo prefetch=5 respeta el límite de 10 conexiones. Batch reservado para drift recovery. Ref: research-technical-patterns.md Topic 1.
**Consequences**: Dos code paths (individual + batch), pero claramente separados.

### ADR-8: Dispatch Agnóstico a Notifier-Service

**Context**: notifier-service existe y usa symfony-notifier. Necesita recibir destinatarios + contenido sin conocer "suscripciones".
**Decision**: enBandeja despacha un `SendNotificationCommand` con: recipients (array de email+userId), editorialId, channel. El contrato es agnóstico al concepto de suscripción.
**Rationale**: Desacoplamiento. notifier-service no necesita saber de enBandeja. El mensaje es autocontenido. Si se cambia el sistema de envío, enBandeja no cambia.
**Consequences**: notifier-service debe aceptar este formato de mensaje (coordinar contrato).

**Message contract (SendNotificationCommand)**:

```json
{
  "recipients": [
    {"email": "user@example.com", "user_id": "user-123"},
    {"email": "other@example.com", "user_id": "user-456"}
  ],
  "editorial_id": "editorial-789",
  "editorial_title": "",
  "channel": "email"
}
```

- `recipients`: array de objetos con `email` (string) y `user_id` (string). Deduplicados por email.
- `editorial_id`: ID del editorial publicado. El notifier-service enriquece con título, URL, imagen.
- `editorial_title`: vacío — el notifier-service lo resuelve desde Jarvis/editorial-service.
- `channel`: siempre `email` en MVP. Extensible a `push`, `sms` en futuro.

---

## 8. Infrastructure

### 8.1 Dependencies

| Componente | Tecnología | Versión |
|-----------|-----------|---------|
| Runtime | PHP | 8.3+ |
| Framework | Symfony | 8.0 |
| DB | PostgreSQL | 16 |
| Queue | RabbitMQ | 3.x |
| ORM | Doctrine | 3.x |
| Async | Symfony Messenger | 8.0 |
| Scheduling | Symfony Scheduler | 8.0 |
| Auth | web-token/jwt-framework | 4.x |
| HTTP Client | Symfony HttpClient | 8.0 |

### 8.2 Environment Variables

| Variable | Purpose | Example |
|----------|---------|---------|
| DATABASE_URL | PostgreSQL connection | postgresql://user:pass@host:5432/enbandeja |
| MESSENGER_TRANSPORT_DSN | RabbitMQ (internal) | amqp://guest:guest@localhost:5672/%2f/enbandeja |
| JARVIS_TRANSPORT_DSN | RabbitMQ (Jarvis events) | amqp://guest:guest@localhost:5672/%2f/jarvis |
| JWT_SECRET | JWT signature key | (shared with auth service) |
| MAILCHIMP_API_KEY | Mailchimp Marketing API | key-us1 |
| MAILCHIMP_LIST_ID | Audience ID | abc123 |
| MAILCHIMP_DATA_CENTER | API region | us1 |
| CORS_ALLOW_ORIGIN | Allowed origins | ^https://.*elconfidencial\.com$ |
| LOCK_DSN | Lock store for Scheduler | postgresql+advisory://localhost:5432/enbandeja |

### 8.3 Deployment

```
┌─────────────────────────────────────────┐
│  Pod: enbandeja-api                     │
│  php-fpm + nginx → SubscriptionController│
│  Replicas: 2+                           │
├─────────────────────────────────────────┤
│  Pod: enbandeja-worker-async            │
│  messenger:consume async scheduler      │
│  Replicas: 1-2                          │
├─────────────────────────────────────────┤
│  Pod: enbandeja-worker-jarvis           │
│  messenger:consume jarvis_events        │
│  Replicas: 1-2                          │
├─────────────────────────────────────────┤
│  Pod: enbandeja-worker-mailchimp        │
│  messenger:consume mailchimp_sync       │
│  Replicas: 1 (prefetch=5)              │
└─────────────────────────────────────────┘
```

4 tipos de pod, cada uno escala según su carga.
