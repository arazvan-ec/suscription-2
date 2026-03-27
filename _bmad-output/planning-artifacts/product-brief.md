# Product Brief: enBandeja

**Date**: 2026-03-26
**Owner**: El Confidencial — Tech Team
**Status**: Draft — Pending PM review

---

## Vision

Permitir a los lectores de El Confidencial seguir a sus periodistas, tags y
secciones favoritas, y recibir notificaciones automáticas cuando se publica
contenido nuevo relacionado. "Tu bandeja, tu contenido."

---

## Problem Statement

Los lectores de El Confidencial no tienen una forma de suscribirse a contenido
específico de sus intereses. El consumo es pasivo: dependen de la home page,
redes sociales, o newsletters genéricas. Esto reduce el engagement y la
recurrencia de visitas.

---

## Target Users

**Lector habitual logueado** de El Confidencial que:
- Tiene cuenta y accede regularmente
- Sigue a periodistas específicos o temas de interés
- Quiere ser notificado cuando se publica contenido relevante
- No quiere ruido: solo contenido que ha elegido seguir

---

## Core Value Proposition

- **Para el lector**: Contenido curado automáticamente según sus intereses
- **Para El Confidencial**: Mayor engagement, recurrencia, y datos de preferencia
- **Para el equipo editorial**: Visibilidad de su audiencia directa de seguidores

---

## Scope

### In Scope (MVP)

1. **Flujo de suscripción** — Seguir/dejar de seguir periodistas, tags, secciones
2. **Flujo de notificación** — Emails automáticos al publicar contenido nuevo
3. **Sincronización Mailchimp** — Gestión de audiencias vía Marketing API
4. **Integración CMS** — Consumo de eventos `editorial.published` desde Jarvis
5. **Integración CDN** — Personalización client-side compatible con Transparent Edge

### Out of Scope (MVP)

- Dashboard de backoffice para gestionar suscripciones
- Métricas de engagement (apertura, CTR)
- Push notifications (web/mobile)
- Preferencias de frecuencia de notificación
- Multisite (solo El Confidencial)
- Implementación del channel Mailchimp en notifier-service (interfaz definida,
  implementación es responsabilidad de otro equipo)

---

## Key Actors & Systems

| Actor/Sistema | Interacción |
|---------------|-------------|
| Lector (browser) | Sigue/deja de seguir vía botón en la página |
| delorian-statics (JS) | Lee cookie, llama API, renderiza botón |
| Jarvis (CMS) | Emite `editorial.published` vía RabbitMQ |
| journalist/tag/section-svc | Proveen flags de entidad habilitada |
| notifier-service | Recibe dispatch de envío (destinatarios + contenido) |
| Mailchimp Marketing API | Sync de audiencias (add/remove miembros) |
| Transparent Edge (CDN) | Sirve HTML cacheado con placeholders |

---

## Success Metrics

| Métrica | Target MVP |
|---------|------------|
| Suscripciones activas (mes 1) | 5,000+ |
| Latencia GET status | < 50ms p95 |
| Latencia POST/DELETE | < 200ms p95 |
| Tiempo publicación → email | < 10 min |
| Tasa de error en sync Mailchimp | < 1% |
| Disponibilidad del servicio | 99.5% |

---

## Constraints

- **Tecnología**: Symfony 8, PHP 8.3+, PostgreSQL, RabbitMQ (ecosistema existente)
- **Auth**: JWT existente vía `X-Auth-Token` header o cookie `accessToken`
- **CDN**: Las páginas están cacheadas; la personalización DEBE ser client-side
- **Solo email**: MVP solo notifica por email, no push
- **Solo El Confidencial**: No multisite en MVP
- **Mailchimp rate limits**: 10 req/s en Marketing API → requiere batching async
- **notifier-service**: Ya existe, usa symfony-notifier; enBandeja despacha
  mensajes pero no es responsable del envío final

---

## Assumptions

1. Los JWT emitidos por el sistema de auth central contienen `sub` (userId) y `email`
2. Los servicios de metadata (journalist, tag, section) expondrán un flag
   indicando si la entidad está habilitada para "seguir"
3. El notifier-service aceptará un mensaje con formato: destinatarios + contenido
   + canal, sin necesitar conocer el concepto de "suscripción"
4. Mailchimp Marketing API está disponible con una cuenta y audiencia configurada
5. El volumen inicial será escenario Medio (~50K suscripciones, ~200 editoriales/día)
