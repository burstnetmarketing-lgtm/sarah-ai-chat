---
name: commit.txt correct path
description: commit.txt must always be written to docs/ai/commit.txt, not project root
type: feedback
---

Always write commit.txt to `docs/ai/commit.txt` (not the project root `commit.txt`).

**Why:** The `scripts/commit.js` reads from `docs/ai/commit.txt` — wrong path means the script can't find the message.

**How to apply:** Every time a task is complete and commit.txt is written, use path `docs/ai/commit.txt`.
