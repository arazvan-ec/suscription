#!/usr/bin/env bash
# parallel-tasks.sh — Execute multiple independent tasks in parallel
#
# Each task gets its own fresh context sub-agent.
# Works with BMAD stories, OpenSpec tasks, or any markdown task file.
#
# IMPORTANT: Only parallelize tasks that do NOT depend on each other.
#
# Usage:
#   ./.claude/scripts/parallel-tasks.sh <task1.md> <task2.md> [task3.md ...]
#
# Environment:
#   MAX_PARALLEL=4    # Max concurrent tasks (default: 4)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRESH_EXEC="$SCRIPT_DIR/fresh-exec.sh"
MAX_PARALLEL="${MAX_PARALLEL:-4}"

# --- Validation ---
if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <task1.md> <task2.md> [task3.md ...]"
    echo ""
    echo "IMPORTANT: Only parallelize tasks that do NOT depend on each other."
    echo "Set MAX_PARALLEL=N to limit concurrency (default: 4)."
    exit 1
fi

if [[ ! -x "$FRESH_EXEC" ]]; then
    echo "ERROR: fresh-exec.sh not found or not executable at: $FRESH_EXEC"
    exit 1
fi

# --- Create log directory ---
LOG_DIR="/tmp/parallel-tasks-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$LOG_DIR"

echo "=== Parallel Tasks Execution ==="
echo "Tasks: $#"
echo "Max parallel: $MAX_PARALLEL"
echo "Logs: $LOG_DIR"
echo "================================="
echo ""

# --- Launch tasks in waves ---
PIDS=()
FILES=()
RUNNING=0

for task_file in "$@"; do
    if [[ ! -f "$task_file" ]]; then
        echo "WARNING: Task file not found, skipping: $task_file"
        continue
    fi

    # Wait if at max parallelism
    while [[ $RUNNING -ge $MAX_PARALLEL ]]; do
        wait -n 2>/dev/null || true
        RUNNING=$((RUNNING - 1))
    done

    task_name="$(basename "$task_file" .md)"
    log_file="$LOG_DIR/$task_name.log"

    echo "Launching: $task_name (log: $log_file)"
    "$FRESH_EXEC" "$task_file" > "$log_file" 2>&1 &
    PIDS+=($!)
    FILES+=("$task_file")
    RUNNING=$((RUNNING + 1))
done

echo ""
echo "All tasks launched. Waiting for completion..."
echo ""

# --- Wait for all to finish and report ---
FAILED=0
for i in "${!PIDS[@]}"; do
    pid="${PIDS[$i]}"
    file="${FILES[$i]}"
    task_name="$(basename "$file" .md)"

    if wait "$pid"; then
        echo "PASS: $task_name (PID $pid)"
    else
        echo "FAIL: $task_name (PID $pid) — check $LOG_DIR/$task_name.log"
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
    echo "Some tasks failed. Review logs and re-run failed tasks individually."
    exit 1
fi
