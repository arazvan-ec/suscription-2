#!/usr/bin/env bash
# fresh-exec.sh — Execute a task file in a fresh Claude Code context
# Usage: ./.claude/scripts/fresh-exec.sh <task-file> [--timeout <seconds>]
#
# Auto-detects relevant Symfony skills by scanning keywords in the task file
# and injects them as context for the Claude session.

set -euo pipefail

TASK_FILE="${1:?Usage: fresh-exec.sh <task-file> [--timeout <seconds>]}"
TIMEOUT="${3:-600}"

if [[ ! -f "$TASK_FILE" ]]; then
    echo "ERROR: Task file not found: $TASK_FILE"
    exit 1
fi

# Auto-detect skills by scanning task file for keywords
SKILLS=""
TASK_CONTENT=$(cat "$TASK_FILE")

detect_skill() {
    local pattern="$1"
    local skill="$2"
    if echo "$TASK_CONTENT" | grep -qiE "$pattern"; then
        SKILLS="$SKILLS --skill $skill"
        echo "  ✓ Detected: $skill"
    fi
}

echo "=== Fresh Exec: $(basename "$TASK_FILE") ==="
echo ""
echo "Scanning for relevant skills..."

detect_skill "controller|endpoint|API|route|REST" "symfony-api"
detect_skill "consumer|Messenger|event|RabbitMQ|async|queue" "symfony-messenger"
detect_skill "entity|repository|migration|Doctrine|schema" "symfony-doctrine"
detect_skill "cache|CDN|Vary|purge|Transparent.Edge" "cdn-caching"

if [[ -z "$SKILLS" ]]; then
    echo "  (no specific skills detected, using defaults)"
fi

echo ""
echo "Starting fresh context execution..."
echo "Task: $TASK_FILE"
echo "Timeout: ${TIMEOUT}s"
echo ""

# Build the prompt from the task file
PROMPT="Execute the following implementation task. Follow all conventions from the injected skills.

--- TASK START ---
$TASK_CONTENT
--- TASK END ---

Instructions:
1. Read and understand the full task
2. Implement each item in the checklist
3. Write tests for new functionality
4. Run tests to verify
5. Report what was implemented and any issues found"

# Execute in a fresh Claude context
# Uses claude CLI with --print for non-interactive mode
if command -v claude &> /dev/null; then
    echo "$PROMPT" | timeout "$TIMEOUT" claude --print $SKILLS 2>&1
    EXIT_CODE=$?
else
    echo "WARNING: Claude CLI not found. Printing task for manual execution."
    echo ""
    echo "$PROMPT"
    EXIT_CODE=0
fi

echo ""
echo "=== Fresh Exec Complete (exit: $EXIT_CODE) ==="

exit $EXIT_CODE
