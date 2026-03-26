#!/usr/bin/env bash
# parallel-tasks.sh — Execute multiple task files in parallel using worktrees
# Usage: ./.claude/scripts/parallel-tasks.sh <task1.md> <task2.md> [task3.md] ...
#
# Each task runs in its own git worktree with an isolated branch.
# Results are collected and reported at the end.

set -euo pipefail

MAX_PARALLEL="${MAX_PARALLEL:-3}"
TIMEOUT="${TIMEOUT:-900}"

if [[ $# -lt 2 ]]; then
    echo "Usage: parallel-tasks.sh <task1.md> <task2.md> [task3.md] ..."
    echo ""
    echo "Environment variables:"
    echo "  MAX_PARALLEL=3    Maximum concurrent tasks (default: 3)"
    echo "  TIMEOUT=900       Timeout per task in seconds (default: 900)"
    exit 1
fi

TASKS=("$@")
TOTAL=${#TASKS[@]}
REPO_ROOT=$(git rev-parse --show-toplevel)
RESULTS_DIR="/tmp/parallel-results-$$"
mkdir -p "$RESULTS_DIR"

echo "=== Parallel Tasks ==="
echo ""
echo "Tasks: $TOTAL"
echo "Max parallel: $MAX_PARALLEL"
echo "Timeout per task: ${TIMEOUT}s"
echo ""

# Track PIDs and their task files
declare -A PIDS
declare -A BRANCHES

# Function to run a single task
run_task() {
    local task_file="$1"
    local task_name=$(basename "$task_file" .md)
    local result_file="$RESULTS_DIR/$task_name.result"
    local branch="parallel/$task_name-$(date +%s)"

    echo "[START] $task_name → branch: $branch"

    # Run in worktree
    timeout "$TIMEOUT" "$REPO_ROOT/.claude/scripts/worktree-exec.sh" \
        "$task_file" "$branch" \
        > "$result_file" 2>&1

    local exit_code=$?

    if [[ $exit_code -eq 0 ]]; then
        echo "[DONE]  $task_name ✓"
    elif [[ $exit_code -eq 124 ]]; then
        echo "[TIMEOUT] $task_name ✗ (exceeded ${TIMEOUT}s)"
    else
        echo "[FAIL]  $task_name ✗ (exit: $exit_code)"
    fi

    echo "$exit_code" > "$RESULTS_DIR/$task_name.exit"
    echo "$branch" > "$RESULTS_DIR/$task_name.branch"
}

# Execute in waves
wave=1
i=0

while [[ $i -lt $TOTAL ]]; do
    echo ""
    echo "--- Wave $wave ---"

    # Launch up to MAX_PARALLEL tasks
    pids=()
    wave_tasks=()

    for ((j=0; j<MAX_PARALLEL && i<TOTAL; j++, i++)); do
        task="${TASKS[$i]}"
        run_task "$task" &
        pids+=($!)
        wave_tasks+=("$(basename "$task" .md)")
    done

    # Wait for wave to complete
    for pid in "${pids[@]}"; do
        wait "$pid" 2>/dev/null || true
    done

    wave=$((wave + 1))
done

# Summary
echo ""
echo "=== Results Summary ==="
echo ""

SUCCESS=0
FAILED=0
BRANCHES_TO_MERGE=()

for task in "${TASKS[@]}"; do
    task_name=$(basename "$task" .md)
    exit_file="$RESULTS_DIR/$task_name.exit"
    branch_file="$RESULTS_DIR/$task_name.branch"

    if [[ -f "$exit_file" ]]; then
        exit_code=$(cat "$exit_file")
        branch=$(cat "$branch_file" 2>/dev/null || echo "unknown")

        if [[ "$exit_code" == "0" ]]; then
            echo "  ✓ $task_name (branch: $branch)"
            SUCCESS=$((SUCCESS + 1))
            BRANCHES_TO_MERGE+=("$branch")
        else
            echo "  ✗ $task_name (exit: $exit_code)"
            FAILED=$((FAILED + 1))
        fi
    else
        echo "  ? $task_name (no result)"
        FAILED=$((FAILED + 1))
    fi
done

echo ""
echo "Success: $SUCCESS / $TOTAL"
echo "Failed: $FAILED / $TOTAL"

if [[ ${#BRANCHES_TO_MERGE[@]} -gt 0 ]]; then
    echo ""
    echo "Branches to merge:"
    for branch in "${BRANCHES_TO_MERGE[@]}"; do
        echo "  git merge $branch"
    done
fi

# Cleanup results
rm -rf "$RESULTS_DIR"

echo ""
echo "=== Parallel Tasks Complete ==="

[[ $FAILED -eq 0 ]] && exit 0 || exit 1
