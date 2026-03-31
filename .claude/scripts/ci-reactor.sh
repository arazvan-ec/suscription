#!/usr/bin/env bash
# ci-reactor.sh — Diagnose and fix CI failures automatically
#
# Reads a CI log, classifies the failure, and attempts a fix.
# Only auto-commits if confidence is HIGH and tests pass.
#
# Usage:
#   ./.claude/scripts/ci-reactor.sh <ci-log-file-or-url>

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PROJECT_CONTEXT="$PROJECT_ROOT/_bmad-output/project-context.md"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <ci-log-file-or-url>"
    exit 1
fi

CI_LOG_SOURCE="$1"

# --- Resolve CI log content ---
if [[ -f "$CI_LOG_SOURCE" ]]; then
    CI_LOG_CONTENT="$(cat "$CI_LOG_SOURCE")"
elif [[ "$CI_LOG_SOURCE" == http* ]]; then
    CI_LOG_CONTENT="$(curl -sL "$CI_LOG_SOURCE" 2>/dev/null || echo "ERROR: Could not fetch $CI_LOG_SOURCE")"
else
    echo "ERROR: CI log not found: $CI_LOG_SOURCE"
    exit 1
fi

# --- Build prompt ---
PROMPT="You are a CI/CD diagnostic agent. Your job is to analyze a CI failure log,
classify the issue, and fix it if possible.

## CRITICAL RULES
- Classify confidence: HIGH (obvious fix, like a typo or missing import),
  MEDIUM (likely fix but needs verification), LOW (unclear root cause).
- Only make changes if you can fix the issue. Do NOT make speculative changes.
- After fixing, run \`php bin/phpunit\` to verify.
- If tests pass after your fix, commit with message: 'fix: [description] (ci-reactor)'
- If tests don't pass or confidence is LOW, do NOT commit. Just report findings.

## PROJECT CONTEXT
<project-context>
$(cat "$PROJECT_CONTEXT" 2>/dev/null || echo "No project context available.")
</project-context>

## CI FAILURE LOG
<ci-log>
$CI_LOG_CONTENT
</ci-log>

## YOUR TASK
1. Read the CI log and identify the failure(s).
2. Classify each failure: type (test failure, lint error, build error, dependency issue)
   and confidence (HIGH/MEDIUM/LOW).
3. For HIGH confidence fixes: make the fix, run tests, commit if green.
4. For MEDIUM/LOW: describe the issue and suggested fix without committing.
5. Output a summary report with:
   - Failures found
   - Classification and confidence
   - Actions taken (or recommended)
"

echo "=== CI Reactor ==="
echo "Source: $CI_LOG_SOURCE"
echo "==================="
echo ""

claude -p "$PROMPT" --allowedTools "Read,Write,Edit,Bash,Glob,Grep"
