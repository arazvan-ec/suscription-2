# Brainstorming Report: enBandeja — Subscription & Notification Service

**Analyst**: Mary (BMAD Analyst)
**Date**: 2026-03-26
**Status**: Complete

---

## 1. Idea Summary

Sistema de suscripciones editoriales que permite a los usuarios de El Confidencial
"seguir" entidades (periodistas, tags, secciones) y recibir notificaciones por
email cuando se publican nuevos contenidos asociados a esas entidades.

El servicio actúa como dominio central de **suscripciones + audiencias + campañas**,
orquestando entre el CMS (Jarvis), los servicios de metadata (journalist-svc,
tag-svc, section-svc), el frontend (delorian-statics) y el servicio de envío
existente (notifier-service).

---

## 2. Context Analysis

### Ecosistema actual

| Servicio | Estado | Rol |
|----------|--------|-----|
| Jarvis (CMS/BFF) | Existente | Publica editoriales, emite eventos `editorial.published` |
| journalist-service | Existente | Metadata y flags de periodistas |
| tag-service | Existente | Metadata y flags de tags |
| section-service | Existente | Metadata y flags de secciones |
| delorian-statics | Existente | JS frontend, lee cookie `accessToken`, renderiza botones |
| notifier-service | Existente | Envío de notificaciones vía symfony-notifier. **No tiene Mailchimp Marketing API** |
| Transparent Edge (CDN) | Existente | Cacheo de páginas con placeholders |
| **enBandeja-service** | **NUEVO** | Suscripciones, audiencias, campañas |

### Dato clave sobre notifier-service

El notifier-service **ya existe** y usa `symfony-notifier` para envío. Sin embargo,
**no tiene integración con Mailchimp Marketing API**. Esto implica que:

- enBandeja necesita sincronizar audiencias con Mailchimp Marketing API directamente
  (gestión de listas/audiencias)
- El envío transaccional puede ir por el notifier-service existente (que usa sus
  propios canales) o necesitar un nuevo provider Mailchimp en notifier
- **Decisión abierta**: ¿enBandeja sincroniza audiencias Y usa Mailchimp para campañas,
  o delega el envío al notifier-service existente añadiendo Mailchimp como provider?

---

## 3. Dimensionamiento

El usuario no tiene números exactos. Aquí van referencias para dimensionar
basadas en medios digitales de tamaño similar:

### Escenarios de referencia

| Métrica | Conservador | Medio | Agresivo |
|---------|-------------|-------|----------|
| Usuarios registrados | 100K | 500K | 2M |
| % que usa "seguir" | 2-5% | 5-10% | 10-20% |
| Suscripciones activas | 2K-5K | 25K-50K | 200K-400K |
| Suscripciones por usuario | 1-3 | 3-5 | 5-10 |
| Editoriales/día | 50-100 | 100-200 | 200-500 |
| Notificaciones/día | 5K-15K | 50K-200K | 500K-2M |

### Implicaciones para el diseño

- **GET /subscriptions (status check)**: Alto tráfico, se ejecuta en cada
  page view de un usuario logueado. Debe ser < 50ms. → Índice compuesto
  en (user_id, entity_type, entity_id) es crítico.

- **POST/DELETE /subscriptions**: Tráfico bajo-medio. Puede tolerar ~200ms
  porque la sincronización con Mailchimp es async.

- **editorial.published consumer**: Ráfagas cuando se publican varios
  editoriales simultáneos (breaking news). Debe procesar sin backpressure.
  → Worker con autoescalado.

- **Campaign processing**: Puede ser diferido 5-10 minutos post-publicación.
  Batch-friendly. → Cron cada minuto + procesamiento async.

### Recomendación de dimensionamiento inicial

Diseñar para el escenario **Medio** (50K suscripciones, 200 editoriales/día)
con capacidad de escalar a **Agresivo** sin cambios arquitecturales.

---

## 4. Extensibilidad de entity types

El sistema debe ser extensible a nuevos tipos de entidades sin cambio de código.

### Opciones analizadas

**Opción A: Enum cerrado en código (journalist, tag, section)**
- Pro: Type-safe, validación en compilación
- Contra: Requiere deploy para añadir tipos
- Cuando: Si los tipos cambian raramente

**Opción B: Tabla de entity_types configurable**
- Pro: Nuevos tipos sin deploy
- Contra: Más complejidad, joins adicionales
- Cuando: Si se prevén cambios frecuentes

**Opción C: Enum con fallback a string (hybrid)**
- Pro: Type-safe para conocidos, acepta nuevos vía config
- Contra: Complejidad intermedia
- Cuando: Balance entre seguridad y flexibilidad

### Recomendación

**Opción A con diseño preparado para migrar a C**. Empezar con enum cerrado
(journalist, tag, section) pero asegurar que:
- La base de datos usa `VARCHAR(50)` no enum de Postgres
- El entity_type viaja como string en la API
- Añadir un nuevo tipo es solo: añadir caso al enum + flag en el servicio externo

---

## 5. Mailchimp Strategy

### Estado actual
- Mailchimp Marketing API para gestión de audiencias (listas, tags, segmentos)
- notifier-service usa symfony-notifier pero NO tiene Mailchimp

### Estrategia propuesta

```
enBandeja-service                          notifier-service
┌──────────────────┐                       ┌──────────────────┐
│ Suscripciones    │                       │ symfony-notifier  │
│ Audiencias       │──sync audiencias──→   │ + MailchimpChannel│
│ Campañas         │──dispatch campaign──→ │ (Transactional)   │
│                  │  (RabbitMQ)           │                   │
│ Mailchimp        │                       │                   │
│ Marketing API    │                       │                   │
│ (audience sync)  │                       │                   │
└──────────────────┘                       └──────────────────┘
```

- **enBandeja** → Mailchimp Marketing API: gestión de audiencias (add/remove members)
- **enBandeja** → notifier-service: dispatch del envío (destinatarios + contenido)
- **notifier-service** necesitará un nuevo channel Mailchimp Transactional (fuera
  del scope de este proyecto, pero hay que tenerlo en cuenta en la interfaz)

### Escalabilidad futura

Si se cambia Mailchimp por otro proveedor:
- La interfaz de audiencia sync es un port en Application layer
- Se reemplaza solo la implementación Infrastructure sin tocar dominio

---

## 6. Flujo detallado del diagrama

### Flujo de Suscripción (user-facing, síncrono)

1. CDN (Transparent Edge) sirve HTML cacheado con placeholder `<div id="follow-btn">`
2. delorian-statics (JS) detecta usuario logueado leyendo cookie `accessToken`
3. JS → `GET /api/v1/subscriptions/{entityType}/{entityId}` con JWT en `X-Auth-Token`
4. enBandeja valida JWT, consulta DB → responde `{subscribed: true/false}`
5. JS renderiza "Seguir" o "Dejar de seguir"
6. Click "Seguir" → `POST /api/v1/subscriptions` → status: active → 200 OK
7. Click "Dejar de seguir" → `DELETE /api/v1/subscriptions/{type}/{id}` → soft delete → 200 OK
8. **Async**: enBandeja sincroniza con Mailchimp Marketing API (add/remove audience member)

### Flujo de Notificación (editorial-triggered, async)

1. Redactor publica en Jarvis → evento `editorial.published` → RabbitMQ
2. enBandeja consume evento
3. Verifica flags habilitados consultando journalist-svc, tag-svc, section-svc
4. Si alguna entidad tiene seguidores activos → crea Campaign
5. Worker (cron, `scheduled_at <= NOW()`) resuelve suscriptores frescos
6. Despacha a notifier-service con destinatarios + contenido
7. notifier-service envía vía Mailchimp Transactional

---

## 7. Risks & Open Questions

### Riesgos identificados

| Riesgo | Impacto | Mitigación |
|--------|---------|------------|
| Mailchimp rate limits (10 req/s Marketing API) | Sync de audiencias lento | Batching + cola dedicada |
| notifier-service no tiene Mailchimp Transactional | No se pueden enviar emails | Definir interfaz de dispatch agnóstica al provider |
| Ráfaga de editoriales (breaking news) | Backpressure en consumer | Prefetch limitado + autoescalado workers |
| Suscripción duplicada (race condition POST) | Datos inconsistentes | Unique constraint DB + upsert logic |
| CDN cachea HTML viejo tras cambio de estado | Botón muestra estado incorrecto | El botón se personaliza client-side, no por CDN |

### Preguntas abiertas

1. ¿El notifier-service aceptará un nuevo tipo de mensaje desde enBandeja o hay
   que definir un contrato nuevo?
2. ¿Los flags de entidad (habilitado para "seguir") ya existen en journalist-svc
   et al., o hay que añadirlos?
3. ¿Hay un servicio de identidad/auth central que emite los JWT, o cada servicio
   los valida independientemente?
4. ¿Mailchimp ya tiene una cuenta/audiencia configurada para El Confidencial?

---

## 8. Scope Recommendation

### MVP (Fase 1 — todo junto según requisito)

- Flujo de suscripción completo (GET/POST/DELETE)
- Flujo de notificación completo (editorial.published → campaign → dispatch)
- Sincronización de audiencias con Mailchimp Marketing API
- Dispatch a notifier-service (interfaz agnóstica, el provider de envío es
  responsabilidad de notifier)

### Post-MVP (Fase 2)

- Dashboard de gestión de suscripciones (backoffice)
- Métricas: tasa de apertura, CTR por tipo de entidad
- Preferencias de frecuencia (diario, semanal, inmediato)
- Nuevos entity types según demanda
- Push notifications (además de email)
