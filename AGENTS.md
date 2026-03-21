# AGENTS.md

## Core Rules
- Follow WordPress plugin standards at all times
- Do not create monolithic code
- Each feature must be implemented in a separate file
- Keep code modular and maintainable
- **All project files must be written in English** — docs, tasks, code comments, commit messages. No exceptions.

---

## Project Structure
- All scripts are located in `/scripts`
- Do not modify `scripts/menu.js` unless explicitly required
- New functionality must not break existing structure
- Follow architecture defined in: docs/ARCHITECTURE.md

---

## Coding Rules
- Follow coding style in: docs/CODING_STYLE.md
- Use clear and consistent naming
- Avoid unnecessary complexity
- Do not introduce unused code

---

## Workflow Rules
- Follow workflow in: docs/WORKFLOW.md
- Use project scripts for actions (commit, push, deploy)
- Do not run manual git commands unless necessary

### When to create a task
- ANY change to code, files, or project structure → create task file + update counter + append to updates.md + write to commit.txt
- Pure consultation (reading files, answering questions, no changes made) → no task needed

### commit.txt — CRITICAL
- This is not optional. This is not a reminder. This is a hard rule.
- The moment you finish making any change — before responding to the user — fill `docs/ai/commit.txt`
- Do not wait for the user to ask. Do not wait to see if more changes are coming.
- Every single change. Every single time. No exceptions.

---

## UI Rules
- Read `docs/UI_GUIDELINES.md` before writing ANY frontend or UI code
- ALL UI is built with Bootstrap 5 — no exceptions
- NEVER use WordPress admin UI classes: `wp-list-table`, `form-table`, `button-primary`, `widefat`, `postbox`, `notice`, or any `wp-*` class
- NEVER use WordPress UI patterns: `<table class="form-table">`, metaboxes, WP notices
- React components use `className`, not `class`

---

## Scenarios
- Check scenarios in: docs/scenarios/
- Read relevant `*.md` files before implementing tasks
- Follow scenario instructions when applicable

---

## Safety Rules
- Do not delete or overwrite existing working code without reason
- Do not change file structure without explicit need
- Prefer extending existing code over rewriting

---

## Output Rules
- Keep responses concise and structured
- Prefer practical implementation over long explanations
- Write production-ready code only