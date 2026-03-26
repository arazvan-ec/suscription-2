---
name: openspec-to-ecc
description: Bridge between OpenSpec change proposals and ECC execution
triggers:
  - openspec-to-ecc
  - bridge
  - transition
---

# OpenSpec to ECC Bridge Skill

## Purpose

Translates OpenSpec change proposals (specs, design, tasks) into ECC-compatible
execution context so that implementation agents have all the information they need.

## When to Use

After `openspec-propose` produces a change proposal and `readiness-gate` passes,
use this skill to prepare the execution context before running fresh-exec.sh or
qa-loop.sh.

## Process

### 1. Gather OpenSpec Artifacts

Read the following files from the change directory:

```
openspec/changes/[feature]/
├── proposal.md          # WHY: motivation and scope
├── specs/               # WHAT: delta markers (ADDED/MODIFIED/REMOVED)
│   ├── api-spec.md
│   ├── data-model.md
│   └── events.md
├── design.md            # HOW: technical approach
└── tasks.md             # Implementation checklist
```

### 2. Build Execution Context

Create a unified context file that ECC agents can consume:

```markdown
# Execution Context: [Feature Name]

## Objective
[From proposal.md — one paragraph]

## Specs Summary
[From specs/ — list of changes with delta markers]

### ADDED
- [New endpoint] POST /subscriptions
- [New entity] Subscription

### MODIFIED
- [Updated] UserController — add follow button rendering
- [Updated] editorial.published handler — check followers

### REMOVED
- [None in this change]

## Technical Design
[From design.md — key decisions and approach]

## Implementation Tasks
[From tasks.md — ordered checklist]

## Relevant Skills
[Auto-detected from task keywords]
- symfony-api (endpoint creation)
- symfony-messenger (event handling)
- symfony-doctrine (entity/repository)

## Constraints
- Must maintain backward compatibility with existing APIs
- Soft delete only (status: active/inactive)
- All new endpoints require JWT auth
```

### 3. Detect Relevant Skills

Scan tasks.md for keywords and list applicable skills:

| Keyword Pattern | Skill |
|----------------|-------|
| controller, endpoint, API, route | symfony-api |
| consumer, Messenger, event, queue, RabbitMQ | symfony-messenger |
| entity, repository, migration, Doctrine | symfony-doctrine |
| cache, CDN, Vary, purge | cdn-caching |

### 4. Output

Write the execution context to:
```
openspec/changes/[feature]/execution-context.md
```

This file is what gets passed to `fresh-exec.sh` or `qa-loop.sh`.

## Example Invocation

```bash
# After openspec-propose and readiness-gate pass:
# Claude generates execution-context.md, then:
./.claude/scripts/fresh-exec.sh openspec/changes/follow-journalist/execution-context.md
```

## Rules

- Always read ALL spec files before building context
- Include delta markers (ADDED/MODIFIED/REMOVED) in the summary
- Auto-detect skills but allow manual override
- Keep execution context under 500 lines
- Include constraints from both proposal.md and design.md
