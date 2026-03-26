#!/usr/bin/env bash
# worktree-exec.sh — Execute a task in an isolated git worktree
# Usage: ./.claude/scripts/worktree-exec.sh <task-file> [branch-name]
#
# Creates a temporary git worktree so changes are isolated from the main
# working directory. Useful for parallel task execution without conflicts.

set -euo pipefail

TASK_FILE="${1:?Usage: worktree-exec.sh <task-file> [branch-name]}"
BRANCH_NAME="${2:-worktree/$(basename "$TASK_FILE" .md)-$(date +%s)}"

if [[ ! -f "$TASK_FILE" ]]; then
    echo "ERROR: Task file not found: $TASK_FILE"
    exit 1
fi

# Setup
REPO_ROOT=$(git rev-parse --show-toplevel)
WORKTREE_DIR="/tmp/worktree-$(basename "$TASK_FILE" .md)-$$"
CURRENT_BRANCH=$(git branch --show-current)

echo "=== Worktree Exec: $(basename "$TASK_FILE") ==="
echo ""
echo "Creating isolated worktree..."
echo "  Branch: $BRANCH_NAME"
echo "  Directory: $WORKTREE_DIR"
echo ""

# Create worktree with a new branch
git worktree add -b "$BRANCH_NAME" "$WORKTREE_DIR" HEAD

cleanup() {
    echo ""
    echo "Cleaning up worktree..."
    cd "$REPO_ROOT"
    git worktree remove "$WORKTREE_DIR" --force 2>/dev/null || true
    echo "Worktree removed."
}

# Cleanup on exit (but not the branch — that's preserved)
trap cleanup EXIT

# Copy task file to worktree if it's a relative path
TASK_IN_WORKTREE="$WORKTREE_DIR/$TASK_FILE"
if [[ ! -f "$TASK_IN_WORKTREE" ]]; then
    cp "$TASK_FILE" "$WORKTREE_DIR/"
    TASK_IN_WORKTREE="$WORKTREE_DIR/$(basename "$TASK_FILE")"
fi

# Execute in worktree
cd "$WORKTREE_DIR"

echo "Executing task in isolated worktree..."
echo ""

if command -v claude &> /dev/null; then
    TASK_CONTENT=$(cat "$TASK_IN_WORKTREE")
    PROMPT="You are working in an isolated git worktree on branch '$BRANCH_NAME'.
Execute the following task:

$TASK_CONTENT

After implementation:
1. Run tests
2. Commit all changes with a descriptive message
3. Report what was done"

    echo "$PROMPT" | claude --print 2>&1
    EXIT_CODE=$?
else
    echo "WARNING: Claude CLI not found."
    echo "Worktree created at: $WORKTREE_DIR"
    echo "Branch: $BRANCH_NAME"
    echo "Execute manually and merge when done."
    EXIT_CODE=0
fi

# Check if there were changes
cd "$WORKTREE_DIR"
if git diff --quiet HEAD && git diff --cached --quiet; then
    echo ""
    echo "No changes made in worktree. Branch will be cleaned up."
    cd "$REPO_ROOT"
    git branch -D "$BRANCH_NAME" 2>/dev/null || true
else
    echo ""
    echo "Changes committed on branch: $BRANCH_NAME"
    echo "To merge: git merge $BRANCH_NAME"
    echo "To review: git log $CURRENT_BRANCH..$BRANCH_NAME"
fi

echo ""
echo "=== Worktree Exec Complete (exit: $EXIT_CODE) ==="

exit $EXIT_CODE
