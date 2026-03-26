# enBandeja — Subscription & Notification Service

## Project

Symfony 8 microservice for managing user subscriptions to editorial entities
(journalists, tags, sections) and sending notifications when new content is published.

## Architecture

Three-layer DDD: `src/Domain/`, `src/Application/`, `src/Infrastructure/`

Key patterns:
- REST API with RFC 7807 errors, JWT auth (X-Auth-Token)
- Symfony Messenger + RabbitMQ for async processing
- Doctrine ORM with PostgreSQL, UUID v7 keys
- CDN-friendly responses (Transparent Edge)

## Workflows

This project uses a unified workflow system: BMAD + ECC + OpenSpec.

| Situation | Flow |
|-----------|------|
| New feature (complex) | BMAD: bmad-analyst → bmad-pm → bmad-architect → stories → fresh-exec |
| Feature in existing code | OpenSpec: /opsx:propose → readiness-gate → qa-loop |
| Bug fix / small change | bmad-quick-dev or direct |

## Custom Skills (7)

- `symfony-api` — REST controller patterns, RFC 7807, auth
- `symfony-messenger` — RabbitMQ, async handlers, events
- `symfony-doctrine` — Entities, repositories, migrations
- `cdn-caching` — CDN headers, Transparent Edge, purge
- `openspec-to-ecc` — Bridge OpenSpec specs to execution context
- `readiness-gate` — Pre-implementation validation
- `deep-research` — Structured technical investigation

## Scripts

```bash
.claude/scripts/fresh-exec.sh <task.md>           # Fresh context per task
.claude/scripts/worktree-exec.sh <task.md>         # Isolated git worktree
.claude/scripts/parallel-tasks.sh <a.md> <b.md>    # Parallel execution
.claude/scripts/qa-loop.sh <task.md> [iterations]   # Implement + evaluate + fix
.claude/scripts/ci-reactor.sh <log>                 # CI failure diagnosis
```

## Conventions

- Entities: rich domain models, private constructor + static `create()` factory
- Soft delete via status field (active/inactive), never hard delete
- One Messenger handler per message class, use `#[AsMessageHandler]`
- Controllers use `#[MapRequestPayload]` for validation
- Commands: `VerbNounCommand`, Queries: `GetNounQuery`
- Tests: PHPUnit, one test class per use case

## Context Files

- `_bmad-output/project-context.md` — Full project context
- `openspec/` — OpenSpec change proposals and specs
- `_bmad-output/planning-artifacts/` — Research and architecture docs
- `_bmad-output/implementation-artifacts/` — Story files for execution
