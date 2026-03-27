# PRD: enBandeja — Subscription & Notification Service

**PM**: John (BMAD PM)
**Date**: 2026-03-26
**Version**: 1.0
**Status**: Draft

---

## 1. Overview

### 1.1 Objetivo

Desarrollar un microservicio (enBandeja-service) que gestione suscripciones de
usuarios a entidades editoriales (periodistas, tags, secciones) y orqueste el
envío de notificaciones cuando se publica contenido nuevo asociado a esas entidades.

### 1.2 Background

El Confidencial publica ~4000 editoriales diarios. Los lectores logueados no
tienen forma de suscribirse a contenido específico. La personalización actual
se limita a newsletters genéricas. enBandeja introduce una relación directa
entre lector y contenido, mejorando engagement y recurrencia.

### 1.3 Scope

enBandeja-service es responsable de:
- **Suscripciones**: CRUD de follow/unfollow de entidades
- **Audiencias**: Sincronización con Mailchimp Marketing API
- **Campañas**: Creación y scheduling de notificaciones editoriales
- **Dispatch**: Envío de destinatarios + contenido al notifier-service

enBandeja-service NO es responsable de:
- Envío final de emails (notifier-service)
- Rendering del botón (delorian-statics)
- Cacheo de páginas (Transparent Edge CDN)
- Gestión de flags de entidad (journalist/tag/section-svc)

---

## 2. Functional Requirements

### FR-1: Consultar estado de suscripción

**Como** lector logueado,
**quiero** saber si ya sigo a una entidad,
**para que** el botón muestre el estado correcto ("Seguir" / "Dejar de seguir").

| Campo | Detalle |
|-------|---------|
| Endpoint | `GET /api/v1/subscriptions/{entityType}/{entityId}` |
| Auth | JWT requerido (X-Auth-Token header o accessToken cookie) |
| Response | `{"subscribed": true/false}` |
| Cache | `Cache-Control: private, no-store` (personalizado, no cachear) |
| Latencia | < 50ms p95 |

**Acceptance Criteria**:
- AC-1.1: Devuelve `subscribed: true` si existe suscripción activa
- AC-1.2: Devuelve `subscribed: false` si no existe o está inactiva
- AC-1.3: Devuelve 401 RFC 7807 si no hay token o es inválido
- AC-1.4: Devuelve 400 RFC 7807 si entityType no es válido

---

### FR-2: Suscribirse a una entidad (Seguir)

**Como** lector logueado,
**quiero** seguir a un periodista, tag o sección,
**para** recibir notificaciones cuando publiquen contenido nuevo.

| Campo | Detalle |
|-------|---------|
| Endpoint | `POST /api/v1/subscriptions` |
| Body | `{"entityType": "journalist", "entityId": "123"}` |
| Auth | JWT requerido |
| Response | `{"subscribed": true}` — 200 OK |

**Acceptance Criteria**:
- AC-2.1: Crea nueva suscripción con status `active`
- AC-2.2: Si ya existe suscripción activa, responde 200 OK (idempotente)
- AC-2.3: Si existe suscripción inactiva, la reactiva a `active`
- AC-2.4: Valida entityType contra tipos permitidos (journalist, tag, section)
- AC-2.5: Valida entityId no vacío
- AC-2.6: Dispara evento `SubscriptionCreatedEvent` (async) para sync Mailchimp
- AC-2.7: Devuelve 401 si no autenticado
- AC-2.8: Devuelve 422 si body inválido (RFC 7807)

---

### FR-3: Cancelar suscripción (Dejar de seguir)

**Como** lector logueado,
**quiero** dejar de seguir una entidad,
**para** no recibir más notificaciones de ella.

| Campo | Detalle |
|-------|---------|
| Endpoint | `DELETE /api/v1/subscriptions/{entityType}/{entityId}` |
| Auth | JWT requerido |
| Response | `{"subscribed": false}` — 200 OK |

**Acceptance Criteria**:
- AC-3.1: Cambia status a `inactive` (soft delete, nunca hard delete)
- AC-3.2: Si no existe o ya es inactiva, responde 200 OK (idempotente)
- AC-3.3: Dispara evento `SubscriptionDeletedEvent` (async) para sync Mailchimp
- AC-3.4: Devuelve 401 si no autenticado
- AC-3.5: Devuelve 400 si entityType inválido

---

### FR-4: Listar suscripciones del usuario

**Como** lector logueado,
**quiero** ver todas mis suscripciones activas,
**para** gestionar a quién sigo.

| Campo | Detalle |
|-------|---------|
| Endpoint | `GET /api/v1/subscriptions` |
| Auth | JWT requerido |
| Response | `{"subscriptions": [{"entity_type": "journalist", "entity_id": "123"}, ...]}` |
| Cache | `Cache-Control: private, no-store` |

**Acceptance Criteria**:
- AC-4.1: Devuelve solo suscripciones con status `active`
- AC-4.2: Cada item incluye entity_type y entity_id
- AC-4.3: Devuelve array vacío si no tiene suscripciones
- AC-4.4: Devuelve 401 si no autenticado

---

### FR-5: Sincronización de audiencias con Mailchimp

**Como** sistema,
**quiero** sincronizar las suscripciones con Mailchimp Marketing API,
**para** mantener las audiencias actualizadas para campañas de email.

**Acceptance Criteria**:
- AC-5.1: Al crear suscripción, añade miembro a la audiencia Mailchimp con tags
- AC-5.2: Al cancelar suscripción, marca miembro como unsubscribed en Mailchimp
- AC-5.3: La sincronización es asíncrona (no bloquea la respuesta al usuario)
- AC-5.4: Si Mailchimp está caído, el mensaje se reintenta (max 3 retries, backoff exponencial)
- AC-5.5: Tags en Mailchimp siguen formato: `{entityType}:{entityId}` (ej: `journalist:123`)

---

### FR-6: Consumo de evento editorial.published

**Como** sistema,
**quiero** procesar eventos de publicación editorial desde Jarvis CMS,
**para** crear campañas de notificación cuando hay seguidores interesados.

**Acceptance Criteria**:
- AC-6.1: Consume mensajes `editorial.published` del exchange RabbitMQ
- AC-6.2: Extrae entidades del editorial (journalistId, tagIds, sectionId)
- AC-6.3: Consulta flags en journalist-svc/tag-svc/section-svc para filtrar entidades habilitadas
- AC-6.4: Verifica si alguna entidad tiene seguidores activos (`hasActiveSubscriptionsForAny`)
- AC-6.5: Si no hay seguidores → descarta (fast-path, sin crear Campaign)
- AC-6.6: Si hay seguidores → crea Campaign con status `pending` y `scheduled_at = NOW() + 5min`
- AC-6.7: Procesa 4000+ eventos/día sin backpressure
- AC-6.8: Mensajes fallidos van a dead-letter queue tras 3 reintentos

---

### FR-7: Procesamiento de campañas

**Como** sistema,
**quiero** procesar campañas pendientes cuando llega su hora programada,
**para** resolver los destinatarios y despachar la notificación.

**Acceptance Criteria**:
- AC-7.1: Un comando cron (`app:campaigns:process`) busca campañas con `status = pending` y `scheduled_at <= NOW()`
- AC-7.2: Para cada campaña, resuelve suscriptores frescos basados en `audience_criteria`
- AC-7.3: Deduplica destinatarios por email (un usuario puede seguir múltiples entidades del mismo editorial)
- AC-7.4: Despacha mensaje a notifier-service vía RabbitMQ con: destinatarios, editorialId, canal (email)
- AC-7.5: Marca campaña como `sent` tras dispatch exitoso
- AC-7.6: Marca campaña como `failed` si el procesamiento falla
- AC-7.7: Previene doble procesamiento (status transition `pending → processing → sent/failed`)
- AC-7.8: El cron puede ejecutarse cada minuto de forma segura (idempotente)

---

### FR-8: Health check

**Como** operador de infraestructura,
**quiero** un endpoint de health check,
**para** monitorizar la disponibilidad del servicio.

| Campo | Detalle |
|-------|---------|
| Endpoint | `GET /health` |
| Auth | Ninguna |
| Response | `{"status": "ok", "service": "enbandeja-service"}` |

**Acceptance Criteria**:
- AC-8.1: Devuelve 200 cuando el servicio está operativo
- AC-8.2: No requiere autenticación

---

## 3. Non-Functional Requirements

### NFR-1: Performance

| Métrica | Requisito |
|---------|-----------|
| GET subscription status | < 50ms p95 |
| POST/DELETE subscription | < 200ms p95 |
| editorial.published processing | < 500ms p95 (excluyendo HTTP a servicios externos) |
| Campaign dispatch latency | < 10 min desde publicación hasta dispatch a notifier |

### NFR-2: Reliability

| Métrica | Requisito |
|---------|-----------|
| Disponibilidad | 99.5% uptime |
| Pérdida de mensajes | 0% (dead-letter queue para mensajes fallidos) |
| Retry strategy | Exponential backoff: 1s, 2s, 4s — max 3 retries |
| Data consistency | Unique constraint previene suscripciones duplicadas |

### NFR-3: Scalability

| Aspecto | Requisito |
|---------|-----------|
| Editoriales/día | 4,000+ sin degradación |
| Suscripciones activas | Hasta 75K a 12 meses |
| Workers concurrentes | Horizontal scaling de consumers |
| Entity types | Extensible sin cambio arquitectural |

### NFR-4: Security

| Aspecto | Requisito |
|---------|-----------|
| Auth | JWT obligatorio en todos los endpoints excepto /health |
| Token source | Header `X-Auth-Token` o cookie `accessToken` |
| Errores | RFC 7807 Problem Details en todas las respuestas de error |
| Data | Un usuario solo puede ver/modificar sus propias suscripciones |
| Soft delete | Nunca hard delete — status inactive para GDPR traceability |

### NFR-5: Observability

| Aspecto | Requisito |
|---------|-----------|
| Logging | Structured JSON logs con context (userId, entityType, campaignId) |
| Trazabilidad | Cada handler loguea entry + exit con resultado |
| Métricas | Contadores: suscripciones creadas/canceladas, campañas procesadas |

### NFR-6: Compatibility

| Aspecto | Requisito |
|---------|-----------|
| Stack | Symfony 8, PHP 8.3+, PostgreSQL 16, RabbitMQ 3.x |
| API format | JSON, Content-Type: application/json |
| Error format | RFC 7807, Content-Type: application/problem+json |
| CDN compat | Endpoints personalizados usan `Cache-Control: private, no-store` |
| CORS | Permitir origen delorian-statics con header X-Auth-Token |

---

## 4. Data Model Summary

### Subscription

| Campo | Tipo | Constraint |
|-------|------|-----------|
| id | UUID v7 | PK |
| user_id | VARCHAR(255) | NOT NULL |
| email | VARCHAR(255) | NOT NULL |
| entity_type | VARCHAR(50) | NOT NULL, enum: journalist/tag/section |
| entity_id | VARCHAR(255) | NOT NULL |
| status | VARCHAR(20) | NOT NULL, default: active |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NOT NULL |

Indexes: `UNIQUE(user_id, entity_type, entity_id)`, `(entity_type, entity_id, status)`, `(user_id, status)`

### Campaign

| Campo | Tipo | Constraint |
|-------|------|-----------|
| id | UUID v7 | PK |
| type | VARCHAR(50) | NOT NULL |
| status | VARCHAR(20) | NOT NULL, default: pending |
| scheduled_at | TIMESTAMP | NOT NULL |
| audience_criteria | JSONB | NOT NULL |
| editorial_id | VARCHAR(255) | NOT NULL |
| created_at | TIMESTAMP | NOT NULL |

Indexes: `(status, scheduled_at)`

---

## 5. External Interfaces

### 5.1 Consumed

| Servicio | Interfaz | Propósito |
|----------|----------|-----------|
| RabbitMQ | Exchange `editorial.published` | Eventos de publicación editorial |
| journalist-svc | REST API | Verificar flag de entidad habilitada |
| tag-svc | REST API | Verificar flag de entidad habilitada |
| section-svc | REST API | Verificar flag de entidad habilitada |

### 5.2 Produced

| Servicio | Interfaz | Propósito |
|----------|----------|-----------|
| Mailchimp Marketing API | REST API | Sync de audiencias (add/remove members) |
| notifier-service | RabbitMQ message | Dispatch de notificación (destinatarios + contenido) |

### 5.3 Exposed

| Consumer | Interfaz | Propósito |
|----------|----------|-----------|
| delorian-statics (JS) | REST API | Subscription CRUD |

---

## 6. Out of Scope

- Dashboard backoffice de gestión de suscripciones
- Métricas de engagement (apertura, CTR)
- Push notifications (web/mobile)
- Preferencias de frecuencia de notificación (diario, semanal)
- Multisite (solo El Confidencial)
- Implementación del channel Mailchimp en notifier-service
- Gestión de flags de entidad en journalist/tag/section-svc

---

## 7. Risks

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| Mailchimp rate limits (10 req/s) | Alta | Medio | Batching async + cola dedicada |
| 4000 editoriales generan backpressure | Media | Alto | Fast-path discard + workers escalables |
| notifier-service no acepta formato | Baja | Alto | Definir contrato early, validar en staging |
| Race condition en doble suscripción | Media | Bajo | UNIQUE constraint + upsert logic |
| JWT expira durante sesión | Media | Bajo | Frontend refresh automático (delorian) |
