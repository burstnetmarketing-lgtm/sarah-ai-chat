// scripts/commit.js
// ─────────────────────────────────────────────
// Commits all staged changes using the message from docs/docs/ai/commit.txt.
// commit.txt is cleared before git add, so it's always empty in the repo.
// Usage:
//   npm run commit
// ─────────────────────────────────────────────

const fs            = require('fs');
const path          = require('path');
const { execSync }  = require('child_process');

const commitFile = path.join(__dirname, '..', 'docs', 'ai', 'commit.txt');

// ── Read message from docs/ai/commit.txt ────────────
if (!fs.existsSync(commitFile)) {
  console.error('Error: docs/ai/commit.txt not found.');
  process.exit(1);
}

const message = fs.readFileSync(commitFile, 'utf8').trim();

if (!message) {
  console.error('Error: docs/ai/commit.txt is empty. Complete a task first.');
  process.exit(1);
}
console.log(`Commit message: "${message}"`);

// ── Clear commit.txt before staging ────────────
fs.writeFileSync(commitFile, '');

// ── Git add + commit ───────────────────────────
try {
  execSync('git add .', { stdio: 'inherit' });
} catch {
  fs.writeFileSync(commitFile, message);
  console.error('Error: git add failed. Message restored to docs/ai/commit.txt.');
  process.exit(1);
}

try {
  execSync(`git commit -m "${message}"`, { stdio: 'inherit' });
  console.log('Commit successful.');
} catch (err) {
  const output = err.stdout ? err.stdout.toString() : '';
  if (output.includes('nothing to commit')) {
    console.log('Nothing to commit. Working tree is clean.');
  } else {
    fs.writeFileSync(commitFile, message);
    console.error('Error: git commit failed. Message restored to docs/ai/commit.txt.');
    process.exit(1);
  }
}
