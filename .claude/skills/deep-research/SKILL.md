---
name: deep-research
description: Deep technical research before planning or implementation
triggers:
  - deep-research
  - investigate
  - research
---

# Deep Research Skill

## Purpose

Conduct thorough technical research on a topic before planning or implementing.
Produces a structured research document that feeds into BMAD planning or
OpenSpec proposals.

## When to Use

- Before BMAD analyst phase for unfamiliar domains
- Before OpenSpec proposal for complex features
- When evaluating technology choices (e.g., push notification providers)
- When investigating existing patterns in the codebase

## Process

### 1. Define Research Scope

```markdown
## Research Question
[Clear, specific question]

## Context
[Why this matters for the current project]

## Constraints
[Time, budget, technology stack limits]
```

### 2. Research Phases

**Phase A: Codebase Analysis**
- Search existing code for related patterns
- Identify current conventions and dependencies
- Map existing entity relationships and data flows

**Phase B: Documentation Review**
- Symfony/Doctrine official docs for relevant features
- Library documentation for dependencies
- Internal wiki/docs if available

**Phase C: Pattern Research**
- Industry patterns for the problem domain
- Similar implementations in open source
- Best practices and anti-patterns

**Phase D: Trade-off Analysis**
- Compare 2-3 viable approaches
- Evaluate: complexity, performance, maintainability, cost
- Consider team familiarity and ecosystem fit

### 3. Output Format

```markdown
# Research: [Topic]

## Summary
[2-3 sentences with the key finding/recommendation]

## Context
[Why this was researched]

## Findings

### Option A: [Name]
- **How**: [Brief technical description]
- **Pros**: [List]
- **Cons**: [List]
- **Complexity**: Low/Medium/High
- **Example**: [Code snippet or reference]

### Option B: [Name]
[Same structure]

### Option C: [Name]
[Same structure]

## Recommendation
[Which option and why]

## Codebase Patterns Found
[Relevant existing code that informed the recommendation]

## Open Questions
[Things that need further investigation or stakeholder input]

## References
[Links to docs, articles, code files]
```

### 4. Save Output

Save to:
- BMAD flow: `_bmad-output/planning-artifacts/research-[topic].md`
- OpenSpec flow: `openspec/changes/[feature]/research-[topic].md`
- Standalone: `docs/research/[topic].md`

## Example Research Topics

- "How to implement real-time subscription status with CDN caching"
- "Mailchimp Marketing API vs Transactional API for notification campaigns"
- "Pagination strategies for Doctrine with large subscriber lists"
- "Event sourcing vs state-based for subscription tracking"

## Rules

- Always start with codebase analysis — don't ignore existing patterns
- Compare at least 2 options before recommending
- Include code examples for the recommended approach
- Keep research documents under 300 lines
- Link to specific files in the codebase when referencing patterns
- Research is not implementation — do NOT write production code here
