# Deep Research

## Purpose
Conduct thorough technical or domain research before planning or implementing.
Produces a structured research report that feeds into BMAD planning or OpenSpec specs.

## When to Apply
- Before `bmad-architect` when exploring unfamiliar technology choices
- Before `/opsx:propose` when the feature involves patterns you haven't used
- When someone says "deep-research [topic]"

## Process

### 1. Clarify Research Question
Restate the question to confirm scope. Distinguish between:
- **Technical research:** "How does X work? What are the tradeoffs?"
- **Domain research:** "How do others solve this problem?"
- **Competitive research:** "What tools/services exist for this?"

### 2. Research Sources
- Search the web for current best practices and documentation
- Search the codebase for existing patterns that relate
- Check project-context.md for constraints that affect the answer

### 3. Produce Structured Report

```markdown
# Research Report: [Topic]

## Question
[Restated research question]

## Findings

### Option A: [Name]
- **How it works:** [explanation]
- **Pros:** [list]
- **Cons:** [list]
- **Fits our stack:** [yes/no/partially — why]

### Option B: [Name]
[Same structure]

## Recommendation
[Which option and why, considering project-context.md constraints]

## References
[Links to docs, articles, repos consulted]
```

### 4. Save Report
Save to `_bmad-output/planning-artifacts/research-[topic-slug].md`

## Output
A research report that any BMAD agent or OpenSpec spec can reference.
The report should be self-contained — anyone reading it should understand
the options and the recommendation without external context.
