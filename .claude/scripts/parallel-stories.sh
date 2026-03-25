#!/usr/bin/env bash
# parallel-stories.sh — Execute multiple independent stories in parallel
#
# Technique: GSD "waves" — independent stories run concurrently.
# Each story gets its own fresh context sub-agent.
#
# IMPORTANT: Only parallelize stories that do NOT depend on each other.
# If story-2 needs code from story-1, run them in series instead.
#
# Usage:
#   ./.claude/scripts/parallel-stories.sh <story1.md> <story2.md> [story3.md ...]
#
# Example:
#   ./.claude/scripts/parallel-stories.sh \
#     _bmad-output/implementation-artifacts/story-health-endpoint.md \
#     _bmad-output/implementation-artifacts/story-user-registration.md

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRESH_DEV="$SCRIPT_DIR/fresh-dev-story.sh"

# --- Validation ---
if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <story1.md> <story2.md> [story3.md ...]"
    echo ""
    echo "IMPORTANT: Only parallelize stories that do NOT depend on each other."
    exit 1
fi

if [[ ! -x "$FRESH_DEV" ]]; then
    echo "ERROR: fresh-dev-story.sh not found or not executable at: $FRESH_DEV"
    exit 1
fi

# --- Create log directory ---
LOG_DIR="/tmp/parallel-stories-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$LOG_DIR"

echo "=== Parallel Stories Execution ==="
echo "Stories: $#"
echo "Logs: $LOG_DIR"
echo "==================================="
echo ""

# --- Launch all stories in parallel ---
PIDS=()
FILES=()

for story_file in "$@"; do
    if [[ ! -f "$story_file" ]]; then
        echo "WARNING: Story file not found, skipping: $story_file"
        continue
    fi

    story_name="$(basename "$story_file" .md)"
    log_file="$LOG_DIR/$story_name.log"

    echo "Launching: $story_name (log: $log_file)"
    "$FRESH_DEV" "$story_file" > "$log_file" 2>&1 &
    PIDS+=($!)
    FILES+=("$story_file")
done

echo ""
echo "All stories launched. Waiting for completion..."
echo ""

# --- Wait for all to finish and report ---
FAILED=0
for i in "${!PIDS[@]}"; do
    pid="${PIDS[$i]}"
    file="${FILES[$i]}"
    story_name="$(basename "$file" .md)"

    if wait "$pid"; then
        echo "PASS: $story_name (PID $pid)"
    else
        echo "FAIL: $story_name (PID $pid) — check $LOG_DIR/$story_name.log"
        FAILED=$((FAILED + 1))
    fi
done

echo ""
echo "=== Results ==="
echo "Total: ${#PIDS[@]}"
echo "Passed: $((${#PIDS[@]} - FAILED))"
echo "Failed: $FAILED"
echo "Logs: $LOG_DIR"
echo "================"

if [[ $FAILED -gt 0 ]]; then
    echo ""
    echo "Some stories failed. Review logs and re-run failed stories individually."
    exit 1
fi
