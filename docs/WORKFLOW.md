# Workflow

This file defines the complete task and commit workflow for this project.
All AI agents must follow these rules exactly on every task.

---

## Step 1 — Implement the Task

- Read the user request carefully
- Check `docs/scenarios/` for relevant scenarios before starting
- Implement the changes
- Keep changes focused — do not refactor unrelated code

---

## Step 2 — Create the Task File

Path format:
```
docs/tasks/{version}/task-{task-number}-{title}.md
```

- `{version}` — current plugin version
- `{task-number}` — 4-digit number from `docs/ai/task-counter.txt`
- `{title}` — lowercase, kebab-case, 3–6 words

Template:
```markdown
# Task {number}: {Title}

- **Task Number:** {number}
- **Title:** {title}
- **Version:** {version}
- **Date:** {YYYY-MM-DD}

---

## User Request

{What the user asked for.}

---

## Implementation Summary

{What was done. Bullet points preferred.}

---

## Affected Files

- `path/to/file.ext` — reason

---

## Archive Notes

{Edge cases, decisions made, alternatives rejected.}

---

## Follow-up Notes

_{Optional. Remove if not needed.}_
```

---

## Step 3 — Increment the Counter

- Read `/docs/ai/task-counter.txt`
- Increment by 1
- Write back (4-digit number only)

---

## Step 4 — Append to Updates Log

File: `docs/updates.md`

Format:
```
{task-number} {Past-tense summary of what was done.}
```

Rules:
- One line per task — no exceptions
- Written in English
- Short, clear, suitable as a commit message

---

## Step 5 — Write to commit.txt

File: `docs/ai/commit.txt`

- Overwrite (never append) — one line only
- Same text as the `updates.md` entry
- The commit script reads this, uses it as the commit message, then clears the file

---

## Available Scripts

| Command | Description |
|---|---|
| `npm run commit` | Commit using message from `docs/ai/commit.txt` |
| `npm run deploy` | Deploy all plugins to LocalWP |
| `npm run deploy -- <slug>` | Deploy a specific plugin |
| `npm run update:<slug> [patch\|minor\|major]` | Bump plugin version |
| `npm run push` | Push to remote |
| `npm run menu` | Interactive script menu |

---

## Rules

- All 5 steps are mandatory — no task is complete without them
- Do not run manual git commands — use `npm run commit` and `npm run push`
- Do not skip steps even for small changes

---

## Language

**Everything in this project must be written in English — no exceptions.**

This applies to:
- All documentation files (`*.md`)
- Task files (`docs/tasks/`)
- Code comments (PHP, JS, CSS)
- Commit messages and `updates.md` entries
- Variable names, function names, class names
- `commit.txt`

User messages may be in any language. Agent responses to the user may be in any language. All project files must be English.
