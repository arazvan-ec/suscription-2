---
name: readiness-gate
description: Pre-implementation gate check to verify specs are complete
triggers:
  - readiness-gate
  - gate check
  - ready to implement
---

# Readiness Gate Skill

## Purpose

Validates that all planning artifacts (BMAD or OpenSpec) are complete and
consistent before starting implementation. Prevents wasted cycles on
incomplete or contradictory specs.

## When to Use

- After BMAD `bmad-check-implementation-readiness` (as additional check)
- After OpenSpec `openspec-propose` (before `openspec-to-ecc`)
- Before any `fresh-exec.sh` or `qa-loop.sh` invocation

## Gate Checks

### Check 1: Artifact Completeness

**For BMAD flow (Flujo A):**
- [ ] PRD.md exists and has FR/NFR sections
- [ ] architecture.md exists and has ADRs
- [ ] At least one story file exists in implementation-artifacts/
- [ ] project-context.md is up to date

**For OpenSpec flow (Flujo B):**
- [ ] proposal.md exists with clear motivation
- [ ] At least one spec file in specs/ with delta markers
- [ ] design.md exists with technical approach
- [ ] tasks.md exists with ordered checklist

### Check 2: Spec Consistency

- [ ] Entity names match across specs, design, and tasks
- [ ] API endpoints in specs match controller references in tasks
- [ ] Event names are consistent between producer and consumer specs
- [ ] No contradictions between proposal scope and task list

### Check 3: Technical Feasibility

- [ ] Referenced services exist (or are marked as new)
- [ ] Database changes are backward-compatible (or migration plan exists)
- [ ] Auth requirements are specified for all endpoints
- [ ] Error scenarios are documented

### Check 4: Dependency Resolution

- [ ] External service dependencies are identified
- [ ] Required Composer packages are listed
- [ ] Environment variables needed are documented
- [ ] No circular dependencies between tasks

## Output Format

```
╔══════════════════════════════════════╗
║       READINESS GATE RESULT         ║
╠══════════════════════════════════════╣
║                                     ║
║  Result: PASS / CONCERNS / FAIL     ║
║                                     ║
╠══════════════════════════════════════╣
║  Checks:                            ║
║  ✅ Artifact Completeness           ║
║  ✅ Spec Consistency                ║
║  ⚠️  Technical Feasibility          ║
║     → Auth not specified for        ║
║       GET /followers endpoint       ║
║  ✅ Dependency Resolution           ║
║                                     ║
╠══════════════════════════════════════╣
║  Action Items (if any):             ║
║  1. Specify auth for GET /followers ║
║  2. Add error response for 404     ║
║                                     ║
╚══════════════════════════════════════╝
```

## Result Meanings

| Result | Meaning | Action |
|--------|---------|--------|
| **PASS** | All checks green | Proceed to implementation |
| **CONCERNS** | Minor gaps found | Fix before implementing, or accept risk |
| **FAIL** | Critical gaps | Must fix before proceeding |

## FAIL Conditions (any one triggers FAIL)

- No tasks.md or story files
- Contradictory specs (e.g., endpoint in spec but not in tasks)
- Missing entity definitions for referenced data
- No auth specification for user-facing endpoints

## Rules

- Run this BEFORE every implementation session
- Never skip the gate check, even for "simple" features
- CONCERNS can be overridden with explicit acknowledgment
- FAIL must be resolved — no exceptions
- Log the gate result for traceability
