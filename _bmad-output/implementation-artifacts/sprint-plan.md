# Sprint Plan: enBandeja-service MVP

**SM**: Scrum Master (BMAD SM)
**Date**: 2026-03-27
**Sprint**: 1 (Full MVP)

---

## Epics

| Epic | Name | Stories |
|------|------|---------|
| 1 | Foundation | 1.1, 1.2 |
| 2 | Application Layer (Subscription) | 2.1, 2.2, 2.3 |
| 3 | Infrastructure Layer (API) | 3.1, 3.2 |
| 4 | Async Flows | 4.1, 4.2, 4.3 |

---

## Implementation Order

Stories are ordered by dependency chain. Each story produces testable output.

```
Story 1.1  Project Skeleton & Configuration
   │
   ├──▶ Story 1.2  Domain Entities, VOs, Events, Repo Interfaces
   │       │
   │       ├──▶ Story 2.1  Subscribe Handler        ─┐
   │       ├──▶ Story 2.2  Unsubscribe Handler       ├── can be parallel
   │       └──▶ Story 2.3  Query Handlers            ─┘
   │               │
   ├──▶ Story 3.1  JWT Auth & RFC 7807 Errors
   │       │
   │       └──▶ Story 3.2  Subscription Controller + Doctrine Repos + Migration
   │               │
   │               ├──▶ Story 4.1  Mailchimp Audience Sync
   │               ├──▶ Story 4.2  Editorial Published Consumer
   │               │       │
   │               └───────└──▶ Story 4.3  Campaign Processing & Scheduler
```

### Sequential execution order:

| # | Story | Name | Depends on | Parallel? |
|---|-------|------|-----------|-----------|
| 1 | 1.1 | Project Skeleton | — | — |
| 2 | 1.2 | Domain Entities | 1.1 | — |
| 3 | 2.1 | Subscribe Handler | 1.2 | ✓ with 2.2, 2.3 |
| 4 | 2.2 | Unsubscribe Handler | 1.2 | ✓ with 2.1, 2.3 |
| 5 | 2.3 | Query Handlers | 1.2 | ✓ with 2.1, 2.2 |
| 6 | 3.1 | JWT Auth & Errors | 1.1 | ✓ with 2.1-2.3 |
| 7 | 3.2 | Controller + Repos + Migration | 2.1-2.3, 3.1 | — |
| 8 | 4.1 | Mailchimp Sync | 1.2, 3.2 | ✓ with 4.2 |
| 9 | 4.2 | Editorial Consumer | 1.2, 3.2 | ✓ with 4.1 |
| 10 | 4.3 | Campaign Processing | 4.2 | — |

### Recommended execution:

```
Wave 1: Story 1.1 (skeleton)
Wave 2: Story 1.2 (domain)
Wave 3: Story 2.1 + 2.2 + 2.3 + 3.1 (parallel — 4 stories)
Wave 4: Story 3.2 (controller — wires everything together)
Wave 5: Story 4.1 + 4.2 (parallel — async flows)
Wave 6: Story 4.3 (campaign processing — depends on 4.2)
```

6 waves, 10 stories total.

---

## PRD Coverage Matrix

| PRD FR | Story | Status |
|--------|-------|--------|
| FR-1: Get subscription status | 2.3, 3.2 | Covered |
| FR-2: Subscribe | 2.1, 3.2 | Covered |
| FR-3: Unsubscribe | 2.2, 3.2 | Covered |
| FR-4: List subscriptions | 2.3, 3.2 | Covered |
| FR-5: Mailchimp sync | 4.1 | Covered |
| FR-6: Editorial consumer | 4.2 | Covered |
| FR-7: Campaign processing | 4.3 | Covered |
| FR-8: Health check | 3.2 | Covered |

All 8 FRs are covered by the story plan.

---

## ADR Coverage Matrix

| ADR | Story |
|-----|-------|
| ADR-1: Three-layer DDD | 1.1 (structure), 1.2 (domain), 2.x (app), 3.x+4.x (infra) |
| ADR-2: Soft delete | 1.2 (entity design) |
| ADR-3: Enum entity types | 1.2 (value objects) |
| ADR-4: Symfony Scheduler | 4.3 (scheduler) |
| ADR-5: Custom serializer | 4.2 (Jarvis events) |
| ADR-6: Dedicated Mailchimp transport | 4.1 (transport config) |
| ADR-7: Hybrid Mailchimp sync | 4.1 (individual) — batch reconciliation is post-MVP |
| ADR-8: Agnostic notifier dispatch | 4.3 (SendNotificationCommand) |

All 8 ADRs are addressed in the story plan.

---

## Execution Notes

- Stories 2.1, 2.2, 2.3 can run via `parallel-tasks.sh` (Wave 3)
- Stories 4.1 and 4.2 can run via `parallel-tasks.sh` (Wave 5)
- Each story includes its own unit tests — no separate "test story"
- After all stories: run code review (bmad-code-review) + security scan
