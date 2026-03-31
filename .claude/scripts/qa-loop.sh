#!/usr/bin/env bash
# qa-loop.sh — Iterative QA evaluation loop (Developer <-> Evaluator)
#
# Technique: Anthropic article "GAN-style" loop
# 1. Implement story with fresh dev sub-agent
# 2. Evaluate with QA sub-agent
# 3. If FAIL: fix sub-agent receives QA report, fixes only reported bugs
# 4. Re-evaluate
# 5. Repeat until PASS or max iterations
#
# Usage:
#   ./.claude/scripts/qa-loop.sh <story-file> [max-iterations]
#
# Example:
#   ./.claude/scripts/qa-loop.sh \
#     _bmad-output/implementation-artifacts/story-health-endpoint.md 3

set -euo pipefail

# --- Configuration ---
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRESH_DEV="$SCRIPT_DIR/fresh-exec.sh"
PROJECT_CONTEXT="$PROJECT_ROOT/_bmad-output/project-context.md"
ARCHITECTURE="$PROJECT_ROOT/_bmad-output/planning-artifacts/architecture.md"
QA_SKILL="$PROJECT_ROOT/.claude/skills/bmad-agent-qa/SKILL.md"

# --- Validation ---
if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <story-file> [max-iterations]"
    echo ""
    echo "Example:"
    echo "  $0 _bmad-output/implementation-artifacts/story-health-endpoint.md 3"
    exit 1
fi

STORY_FILE="$1"
MAX_ITERATIONS="${2:-3}"

if [[ ! -f "$STORY_FILE" ]]; then
    echo "ERROR: Story file not found: $STORY_FILE"
    exit 1
fi

if [[ ! -f "$QA_SKILL" ]]; then
    echo "ERROR: QA skill not found: $QA_SKILL"
    echo "Ensure BMAD QA agent is installed."
    exit 1
fi

# --- Log directory ---
STORY_NAME="$(basename "$STORY_FILE" .md)"
LOG_DIR="/tmp/qa-loop-$STORY_NAME-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$LOG_DIR"

echo "=== QA Loop ==="
echo "Story: $STORY_FILE"
echo "Max iterations: $MAX_ITERATIONS"
echo "Logs: $LOG_DIR"
echo "================"
echo ""

# --- Step 1: Initial implementation ---
echo ">>> Iteration 0: Initial implementation"
"$FRESH_DEV" "$STORY_FILE" > "$LOG_DIR/dev-iteration-0.log" 2>&1
echo "Implementation complete. Log: $LOG_DIR/dev-iteration-0.log"
echo ""

# --- Build QA evaluation prompt ---
build_qa_prompt() {
    local iteration="$1"
    local previous_report="${2:-}"

    local prompt="You are a QA engineer. Your job is to find problems, not to approve.

## QA EVALUATION GUIDELINES
$(cat "$QA_SKILL")

## PROJECT CONTEXT
<project-context>
$(cat "$PROJECT_CONTEXT")
</project-context>

## STORY BEING EVALUATED
<story>
$(cat "$STORY_FILE")
</story>
"

    if [[ -n "$previous_report" && -f "$previous_report" ]]; then
        prompt+="
## PREVIOUS QA REPORT (bugs should be fixed now)
<previous-report>
$(cat "$previous_report")
</previous-report>

Verify that ALL previously reported bugs are actually fixed.
Also look for NEW bugs that may have been introduced by the fixes.
"
    fi

    prompt+="
## YOUR TASK
1. Run \`php bin/phpunit\` to check test results.
2. Review the implementation against the story's acceptance criteria.
3. Test the API endpoints if applicable (use curl or Symfony test client).
4. Score each criterion from 1-10.
5. If ANY criterion scores below 6: output VERDICT: FAIL with detailed bug reports.
6. If ALL criteria score 6+: output VERDICT: PASS.

IMPORTANT: Output your verdict on a line by itself as either:
  VERDICT: PASS
  VERDICT: FAIL

Each bug must include exact steps to reproduce.
"

    echo "$prompt"
}

# --- Build fix prompt ---
build_fix_prompt() {
    local qa_report="$1"

    local prompt="You are a senior developer fixing bugs reported by QA.

## CRITICAL RULES
- Fix ONLY the bugs listed in the QA report. Nothing else.
- Do NOT refactor unrelated code.
- Do NOT add features not in the original story.
- Run \`php bin/phpunit\` after fixes to verify tests still pass.
- Make atomic commits for each fix.

## PROJECT CONTEXT
<project-context>
$(cat "$PROJECT_CONTEXT")
</project-context>
"

    if [[ -f "$ARCHITECTURE" ]]; then
        prompt+="
## ARCHITECTURE
<architecture>
$(cat "$ARCHITECTURE")
</architecture>
"
    fi

    prompt+="
## ORIGINAL STORY
<story>
$(cat "$STORY_FILE")
</story>

## QA REPORT — BUGS TO FIX
<qa-report>
$(cat "$qa_report")
</qa-report>

## YOUR TASK
1. Read the QA report carefully.
2. For each bug: understand it, write a test that reproduces it, fix it.
3. Run \`php bin/phpunit\` to verify all tests pass.
4. Commit each fix atomically.
5. Summarize what you fixed.
"

    echo "$prompt"
}

# --- QA Loop ---
for iteration in $(seq 1 "$MAX_ITERATIONS"); do
    echo ">>> Iteration $iteration: QA Evaluation"

    qa_report="$LOG_DIR/qa-report-iteration-$iteration.log"
    previous_report=""
    if [[ $iteration -gt 1 ]]; then
        previous_report="$LOG_DIR/qa-report-iteration-$((iteration - 1)).log"
    fi

    qa_prompt="$(build_qa_prompt "$iteration" "$previous_report")"
    claude -p "$qa_prompt" --allowedTools "Read,Bash,Glob,Grep" > "$qa_report" 2>&1

    echo "QA report: $qa_report"

    # Check verdict
    if grep -q "VERDICT: PASS" "$qa_report"; then
        echo ""
        echo "=== PASS at iteration $iteration ==="
        echo "Story implementation verified by QA."
        exit 0
    fi

    echo "VERDICT: FAIL — bugs found."

    if [[ $iteration -eq $MAX_ITERATIONS ]]; then
        echo ""
        echo "=== FAIL after $MAX_ITERATIONS iterations ==="
        echo "Max iterations reached. Manual intervention required."
        echo "Last QA report: $qa_report"
        exit 1
    fi

    # Fix bugs
    echo ""
    echo ">>> Iteration $iteration: Fixing bugs"
    fix_prompt="$(build_fix_prompt "$qa_report")"
    claude -p "$fix_prompt" --allowedTools "Read,Write,Edit,Bash,Glob,Grep" \
        > "$LOG_DIR/fix-iteration-$iteration.log" 2>&1
    echo "Fix log: $LOG_DIR/fix-iteration-$iteration.log"
    echo ""
done
