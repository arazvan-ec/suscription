#!/usr/bin/env bash
# fresh-dev-story.sh — Execute a BMAD story with a fresh context sub-agent
#
# Technique: GSD "fresh context per task"
# Each story gets a brand new sub-agent with 200k tokens of clean context.
# No context rot from previous stories.
#
# Usage:
#   ./.claude/scripts/fresh-dev-story.sh <story-file> [extra-context-files...]
#
# Example:
#   ./.claude/scripts/fresh-dev-story.sh \
#     _bmad-output/implementation-artifacts/story-health-endpoint.md
#
#   ./.claude/scripts/fresh-dev-story.sh \
#     _bmad-output/implementation-artifacts/story-health-endpoint.md \
#     _bmad-output/planning-artifacts/architecture.md

set -euo pipefail

# --- Configuration ---
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PROJECT_CONTEXT="$PROJECT_ROOT/_bmad-output/project-context.md"
ARCHITECTURE="$PROJECT_ROOT/_bmad-output/planning-artifacts/architecture.md"

# --- Validation ---
if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <story-file> [extra-context-files...]"
    echo ""
    echo "Example:"
    echo "  $0 _bmad-output/implementation-artifacts/story-health-endpoint.md"
    exit 1
fi

STORY_FILE="$1"
shift

if [[ ! -f "$STORY_FILE" ]]; then
    echo "ERROR: Story file not found: $STORY_FILE"
    exit 1
fi

if [[ ! -f "$PROJECT_CONTEXT" ]]; then
    echo "ERROR: project-context.md not found at: $PROJECT_CONTEXT"
    echo "Run bmad-generate-project-context first."
    exit 1
fi

# --- Build the prompt with inline context ---
PROMPT="You are a senior developer implementing a story for a Symfony project.

## CRITICAL RULES
- Implement ONLY what the story asks. Nothing more.
- Write tests FIRST (TDD). Use PHPUnit.
- Make atomic commits: one logical change per commit.
- Follow the coding conventions in the project context.
- Do NOT modify files unrelated to the story.
- If something is unclear, make a reasonable decision and document it in a commit message.

## PROJECT CONTEXT
<project-context>
$(cat "$PROJECT_CONTEXT")
</project-context>
"

# Add architecture if it exists
if [[ -f "$ARCHITECTURE" ]]; then
    PROMPT+="
## ARCHITECTURE
<architecture>
$(cat "$ARCHITECTURE")
</architecture>
"
fi

# Add any extra context files
for extra_file in "$@"; do
    if [[ -f "$extra_file" ]]; then
        PROMPT+="
## ADDITIONAL CONTEXT: $(basename "$extra_file")
<additional-context>
$(cat "$extra_file")
</additional-context>
"
    else
        echo "WARNING: Extra context file not found, skipping: $extra_file"
    fi
done

# Add the story
PROMPT+="
## STORY TO IMPLEMENT
<story>
$(cat "$STORY_FILE")
</story>

## YOUR TASK
1. Read and understand the story and its acceptance criteria.
2. Plan the implementation (list the files you'll create/modify).
3. Write the tests first (PHPUnit).
4. Implement the code to make the tests pass.
5. Run \`php bin/phpunit\` to verify all tests pass.
6. Make atomic commits for each logical change.
7. Summarize what you did and any decisions you made.
"

# --- Execute ---
echo "=== Fresh Dev Story ==="
echo "Story: $STORY_FILE"
echo "Project context: $PROJECT_CONTEXT"
[[ -f "$ARCHITECTURE" ]] && echo "Architecture: $ARCHITECTURE"
echo "Extra files: $*"
echo "========================"
echo ""

claude -p "$PROMPT" --allowedTools "Read,Write,Edit,Bash,Glob,Grep"
