# Story 4.3: Campaign Processing & Scheduler

**Epic**: 4 — Async Flows
**Priority**: P1
**Estimated effort**: Large
**Depends on**: Story 1.2, 3.2, 4.2

---

## Objective

Implement the campaign processing pipeline: Symfony Scheduler dispatches
ProcessCampaignCommand for pending campaigns, handler resolves subscribers
and dispatches to notifier-service.

## Context

- Ref: PRD FR-7 (Procesamiento de campañas)
- Ref: architecture.md Section 4.3 (Campaign Processing)
- Ref: architecture.md ADR-4 (Symfony Scheduler)
- Ref: research-technical-patterns.md Topic 2 (Campaign scheduling)
- Exactly-once: Scheduler lock + SELECT FOR UPDATE SKIP LOCKED + status transition

## Tasks

### Commands

- [ ] `src/Application/Command/ProcessCampaignCommand.php` — final readonly: campaignId (string)
- [ ] `src/Application/Command/SendNotificationCommand.php`:
  - final readonly: recipients (array of {email, user_id}), editorialId, editorialTitle, channel
  - This is the contract with notifier-service (ADR-8)

### Scheduler

- [ ] `src/Infrastructure/Scheduler/CampaignScheduleProvider.php`:
  - `#[AsSchedule('campaigns')]`
  - Implements ScheduleProviderInterface
  - Returns Schedule with:
    - `RecurringMessage::every('60 seconds', new ProcessPendingCampaignsMessage())`
    - `->stateful($cache)` — remembers last run
    - `->lock($lockFactory->createLock('campaign-scheduler'))` — one pod at a time

- [ ] `src/Infrastructure/Scheduler/ProcessPendingCampaignsMessage.php`:
  - Empty marker message dispatched by the scheduler

- [ ] `src/Infrastructure/Messenger/ProcessPendingCampaignsHandler.php`:
  - `#[AsMessageHandler]`
  - Query `findReadyToProcess()` — campaigns with status=pending AND scheduled_at <= NOW
  - For each campaign: dispatch `ProcessCampaignCommand` to async transport
  - Log count of dispatched campaigns

### Campaign Handler

- [ ] `src/Infrastructure/Messenger/ProcessCampaignHandler.php`:
  - `#[AsMessageHandler]`
  - Logic:
    1. Load campaign by ID, verify status is pending
    2. `markAsProcessing()`, save (optimistic claim)
    3. `findActiveByEntities(audienceCriteria)` → fresh subscriber list
    4. Deduplicate by email
    5. If empty → `markAsSent()`, save, return
    6. Dispatch `SendNotificationCommand` with recipients + editorialId
    7. `markAsSent()`, save
    8. On exception: `markAsFailed()`, save, re-throw
  - Wrap in try/catch for the failed path

### Fallback Console Command

- [ ] `src/Infrastructure/Console/ProcessPendingCampaignsCommand.php`:
  - `app:campaigns:process` — Fallback cron if Scheduler is not available
  - Same logic as ProcessPendingCampaignsHandler but as console command
  - Uses LockableTrait to prevent concurrent execution

### Configuration

- [ ] `config/packages/lock.yaml`:
  - Configure lock store using LOCK_DSN env var
- [ ] Update `config/packages/messenger.yaml`:
  - Route ProcessCampaignCommand → async
  - Route SendNotificationCommand → async
  - Add `scheduler_campaigns` transport

### Unit Tests

- [ ] `tests/Infrastructure/Messenger/ProcessCampaignHandlerTest.php`:
  - test processes campaign: resolves subscribers, dispatches notification, marks sent
  - test marks campaign as sent when no subscribers (empty audience)
  - test marks campaign as failed on exception
  - test skips already-processed campaign (not pending)
  - test deduplicates recipients by email

- [ ] `tests/Infrastructure/Scheduler/CampaignScheduleProviderTest.php`:
  - test schedule returns recurring message every 60s

## Acceptance Criteria

- PRD AC-7.1: Scheduler finds pending campaigns where scheduled_at <= NOW ✓
- PRD AC-7.2: Resolves fresh subscribers from audience_criteria ✓
- PRD AC-7.3: Deduplicates by email ✓
- PRD AC-7.4: Dispatches SendNotificationCommand to notifier-service ✓
- PRD AC-7.5: Marks campaign as sent ✓
- PRD AC-7.6: Marks campaign as failed on error ✓
- PRD AC-7.7: Prevents double-processing (status transition) ✓
- PRD AC-7.8: Safe to run every minute (idempotent) ✓
- Symfony Scheduler with stateful + lock (ADR-4) ✓
- All tests pass
