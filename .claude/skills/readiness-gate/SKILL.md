# Readiness Gate

## Purpose
Pre-implementation check that verifies specs are complete enough to start coding.
Prevents wasting execution time on incomplete or ambiguous specifications.

## When to Apply
Before running fresh-exec.sh, qa-loop.sh, or any implementation step.
Works with both BMAD stories and OpenSpec change specs.

## Process

### 1. Detect Spec Type
- If path contains `_bmad-output/` → BMAD story
- If path contains `openspec/changes/` → OpenSpec change
- Otherwise → generic task file

### 2. Check Completeness

**For BMAD stories, verify:**
- [ ] Has acceptance criteria (at least 3 concrete criteria)
- [ ] Has clear scope (what's in, what's out)
- [ ] References architecture decisions if relevant
- [ ] Dependencies are listed and resolved (not blocked)
- [ ] Estimation exists (XS/S/M/L/XL)

**For OpenSpec changes, verify:**
- [ ] Has proposal.md with clear rationale
- [ ] Has at least one spec file with delta markers (ADDED/MODIFIED/REMOVED)
- [ ] Has design.md with implementation approach
- [ ] Has tasks.md with concrete checklist
- [ ] Delta markers are specific (not just "modify the service")

**For generic tasks, verify:**
- [ ] Has clear description of what to build
- [ ] Has acceptance criteria or success definition
- [ ] References enough context to implement without guessing

### 3. Report

**PASS** — All checks green. Safe to implement.

**CONCERNS** — Some checks yellow. List what's missing. Ask if user wants to proceed anyway.

**FAIL** — Critical gaps. List what's missing. Recommend going back to planning.

## Output Format
```
=== Readiness Gate ===
Spec type: BMAD story / OpenSpec change / Generic
File: [path]

Checks:
  [PASS] Acceptance criteria: 5 concrete criteria found
  [PASS] Scope: clearly defined
  [WARN] Architecture: no ADR reference (may not be needed)
  [PASS] Dependencies: none listed
  [PASS] Estimation: M

Verdict: PASS (1 warning)
Ready to implement.
======================
```
