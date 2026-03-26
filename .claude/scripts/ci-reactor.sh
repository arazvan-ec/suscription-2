#!/usr/bin/env bash
# ci-reactor.sh — Diagnose and fix CI failures automatically
# Usage: ./.claude/scripts/ci-reactor.sh <ci-log-file-or-url>
#
# Analyzes CI failure logs, classifies the fix confidence,
# and optionally auto-commits if confidence is HIGH and tests pass.

set -euo pipefail

CI_LOG="${1:?Usage: ci-reactor.sh <ci-log-file-or-url>}"
AUTO_COMMIT="${AUTO_COMMIT:-true}"
TIMEOUT="${TIMEOUT:-600}"

echo "=== CI Reactor ==="
echo ""

# Fetch log content
if [[ "$CI_LOG" == http* ]]; then
    echo "Fetching CI log from URL..."
    LOG_CONTENT=$(curl -sL "$CI_LOG" 2>&1) || {
        echo "ERROR: Failed to fetch CI log from: $CI_LOG"
        exit 1
    }
elif [[ -f "$CI_LOG" ]]; then
    LOG_CONTENT=$(cat "$CI_LOG")
else
    echo "ERROR: CI log not found: $CI_LOG"
    exit 1
fi

echo "Log size: $(echo "$LOG_CONTENT" | wc -l) lines"
echo ""

# Classify and fix
PROMPT="You are a CI failure diagnostician. Analyze the following CI log and:

1. CLASSIFY the failure:
   - Type: test_failure | lint_error | type_error | dependency | config | infra
   - Root cause: one-line description

2. ASSESS fix confidence:
   - HIGH: Clear, isolated fix (typo, missing import, simple test fix)
   - MEDIUM: Likely fix but could have side effects
   - LOW: Complex issue requiring architectural changes

3. If confidence is HIGH or MEDIUM, implement the fix:
   - Make the minimal change needed
   - Run tests to verify the fix works
   - Report the exact changes made

4. Output a summary line at the end:
   CONFIDENCE: HIGH|MEDIUM|LOW
   FIX_APPLIED: true|false
   TESTS_PASS: true|false

--- CI LOG START ---
$(echo "$LOG_CONTENT" | head -500)
--- CI LOG END ---

Important: Only fix code issues. Do not modify CI configuration, Docker setup,
or infrastructure unless the CI log clearly shows a config typo."

if ! command -v claude &> /dev/null; then
    echo "WARNING: Claude CLI not found."
    echo "Manual diagnosis needed for:"
    echo "$CI_LOG"
    exit 1
fi

echo "Analyzing CI failure..."
echo ""

RESULT=$(echo "$PROMPT" | timeout "$TIMEOUT" claude --print 2>&1) || true
echo "$RESULT"

# Parse results
CONFIDENCE=$(echo "$RESULT" | grep -oP 'CONFIDENCE:\s*\K\w+' | tail -1 || echo "UNKNOWN")
FIX_APPLIED=$(echo "$RESULT" | grep -oP 'FIX_APPLIED:\s*\K\w+' | tail -1 || echo "false")
TESTS_PASS=$(echo "$RESULT" | grep -oP 'TESTS_PASS:\s*\K\w+' | tail -1 || echo "false")

echo ""
echo "=== CI Reactor Summary ==="
echo ""
echo "Confidence: $CONFIDENCE"
echo "Fix applied: $FIX_APPLIED"
echo "Tests pass: $TESTS_PASS"

# Auto-commit logic
if [[ "$AUTO_COMMIT" == "true" && "$CONFIDENCE" == "HIGH" && "$FIX_APPLIED" == "true" && "$TESTS_PASS" == "true" ]]; then
    echo ""
    echo "High confidence fix with passing tests — auto-committing..."

    if ! git diff --quiet; then
        git add -A
        git commit -m "fix(ci): auto-fix CI failure

Diagnosed by ci-reactor.sh
Confidence: HIGH
Source: $CI_LOG"
        echo "Committed. Ready to push."
    else
        echo "No changes to commit (fix may have been committed by Claude)."
    fi
elif [[ "$FIX_APPLIED" == "true" ]]; then
    echo ""
    echo "Fix applied but confidence is $CONFIDENCE — manual review recommended."
    echo "Review changes: git diff"
    echo "If satisfied: git add -A && git commit -m 'fix(ci): ...' && git push"
else
    echo ""
    echo "No fix applied. Manual intervention required."
fi

exit 0
