# Copilot instructions

Project guidance lives in `CLAUDE.md` at the repository root (overview, commands, architecture, conventions) and in the task-specific skills under `.claude/skills/` (running tests, E2E, the demo store, extension points, caching, i18n, release, readme). Read the relevant ones before changing code.

## Working principles

Behavioral guidelines to reduce common coding mistakes. They bias toward caution over speed — for trivial tasks, use judgment.

### 1. Think before coding

Don't assume. Don't hide confusion. Surface tradeoffs. State assumptions explicitly; if uncertain, ask. If multiple interpretations exist, present them — don't pick silently. If a simpler approach exists, say so. If something is unclear, stop and ask.

### 2. Simplicity first

Minimum code that solves the problem. No features beyond what was asked, no abstractions for single-use code, no unrequested "flexibility", no error handling for impossible scenarios. If you write 200 lines and it could be 50, rewrite it.

### 3. Surgical changes

Touch only what you must. Don't "improve" adjacent code or formatting, don't refactor what isn't broken, match existing style. Remove only the imports/variables your own changes orphaned; mention pre-existing dead code rather than deleting it. Every changed line should trace directly to the request.

### 4. Goal-driven execution

Define success criteria and loop until verified. Turn "add validation" into "write tests for invalid inputs, then make them pass." For multi-step tasks, state a brief plan with a verify check per step.
