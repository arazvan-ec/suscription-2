#!/usr/bin/env bash
# qa-loop.sh — Implement + Evaluate + Fix cycle
# Usage: ./.claude/scripts/qa-loop.sh <task-file> [max-iterations]
#
# Runs an implement-evaluate-fix loop:
# 1. Implement the task
# 2. Run tests and static analysis
# 3. If issues found, fix them
# 4. Repeat until clean or max iterations reached

set -euo pipefail

TASK_FILE="${1:?Usage: qa-loop.sh <task-file> [max-iterations]}"
MAX_ITERATIONS="${2:-3}"
TIMEOUT="${TIMEOUT:-600}"

if [[ ! -f "$TASK_FILE" ]]; then
    echo "ERROR: Task file not found: $TASK_FILE"
    exit 1
fi

TASK_CONTENT=$(cat "$TASK_FILE")
ITERATION=0
CONVERGED=false

echo "=== QA Loop: $(basename "$TASK_FILE") ==="
echo ""
echo "Max iterations: $MAX_ITERATIONS"
echo "Timeout per iteration: ${TIMEOUT}s"
echo ""

# Phase 1: Initial implementation
echo "--- Iteration 1: Implement ---"
echo ""

IMPLEMENT_PROMPT="Execute the following implementation task. Follow all project conventions.

--- TASK ---
$TASK_CONTENT
--- END TASK ---

After implementing:
1. Run all tests (phpunit)
2. Run static analysis if available (phpstan, psalm)
3. Report the results clearly, including any failures"

if command -v claude &> /dev/null; then
    RESULT=$(echo "$IMPLEMENT_PROMPT" | timeout "$TIMEOUT" claude --print 2>&1) || true
    echo "$RESULT"
else
    echo "WARNING: Claude CLI not found. Running manual QA loop."
    echo "$IMPLEMENT_PROMPT"
    exit 0
fi

ITERATION=1

# Phase 2: Evaluate + Fix loop
while [[ $ITERATION -lt $MAX_ITERATIONS ]]; do
    ITERATION=$((ITERATION + 1))

    echo ""
    echo "--- Iteration $ITERATION: Evaluate + Fix ---"
    echo ""

    # Run evaluation
    EVAL_PROMPT="Review the current state of the implementation for this task:

--- TASK ---
$TASK_CONTENT
--- END TASK ---

Evaluation steps:
1. Run the test suite: ./vendor/bin/phpunit (or php bin/phpunit)
2. Run static analysis if configured: ./vendor/bin/phpstan analyse
3. Check for coding standard violations: ./vendor/bin/php-cs-fixer fix --dry-run --diff
4. Verify all task checklist items are complete

If ALL checks pass, respond with exactly: QA_PASS
If there are failures, fix them and run the checks again.
After fixing, report what was fixed and the new test results."

    RESULT=$(echo "$EVAL_PROMPT" | timeout "$TIMEOUT" claude --print 2>&1) || true
    echo "$RESULT"

    # Check if QA passed
    if echo "$RESULT" | grep -q "QA_PASS"; then
        CONVERGED=true
        break
    fi

    echo ""
    echo "Issues found, continuing to next iteration..."
done

echo ""
echo "=== QA Loop Summary ==="
echo ""
echo "Iterations: $ITERATION / $MAX_ITERATIONS"

if [[ "$CONVERGED" == "true" ]]; then
    echo "Result: CONVERGED ✓"
    echo "All checks passed."
    exit 0
else
    echo "Result: DID NOT CONVERGE ✗"
    echo "Manual intervention required."
    echo ""
    echo "Suggestions:"
    echo "  1. Review the remaining failures"
    echo "  2. Check if the task spec has design issues"
    echo "  3. Increase max iterations: qa-loop.sh $TASK_FILE $((MAX_ITERATIONS + 2))"
    exit 1
fi
