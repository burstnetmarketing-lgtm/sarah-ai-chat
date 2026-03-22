// scripts/commit.js
// ─────────────────────────────────────────────
// Commits all staged changes using the message from docs/docs/ai/commit.txt.
// commit.txt is cleared before git add, so it's always empty in the repo.
// Usage:
//   npm run commit
// ─────────────────────────────────────────────

const fs                        = require('fs');
const path                      = require('path');
const readline                  = require('readline');
const { execSync }              = require('child_process');

const commitFile = path.join(__dirname, '..', 'docs', 'ai', 'commit.txt');

// ── Read message from docs/ai/commit.txt ────────────
if (!fs.existsSync(commitFile)) {
  console.error('Error: docs/ai/commit.txt not found.');
  process.exit(1);
}

let message = fs.readFileSync(commitFile, 'utf8').trim();

const YES_KEYS = ['y', '1', '\u06f1' /* ۱ */];
const NO_KEYS  = ['n', '2', '\u06f2' /* ۲ */];

if (!message) {
  process.stdout.write('docs/ai/commit.txt is empty. Type the commit message manually? [y/n / 1/2]: ');
  process.stdin.resume();
  process.stdin.setRawMode(true);
  process.stdin.once('data', key => {
    const answer = key.toString('utf8').toLowerCase().trim();
    process.stdout.write(answer + '\n');
    process.stdin.setRawMode(false);

    if (!YES_KEYS.includes(answer)) {
      console.log('Aborted. Complete a task first or write a commit message manually.');
      process.exit(0);
    }

    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    rl.question('Commit message: ', manualMessage => {
      rl.close();
      manualMessage = manualMessage.trim();
      if (!manualMessage) {
        console.error('Error: No message entered. Aborted.');
        process.exit(1);
      }
      doCommit(manualMessage);
    });
  });
} else {
  console.log(`Commit message: "${message}"`);
  doCommit(message);
}

function doCommit(msg) {
  // ── Clear commit.txt before staging ────────────
  fs.writeFileSync(commitFile, '');

  // ── Git add + commit ───────────────────────────
  try {
    execSync('git add .', { stdio: 'inherit' });
  } catch {
    fs.writeFileSync(commitFile, msg);
    console.error('Error: git add failed. Message restored to docs/ai/commit.txt.');
    process.exit(1);
  }

  try {
    execSync(`git commit -m "${msg}"`, { stdio: 'inherit' });
    console.log('Commit successful.');
  } catch (err) {
    const output = err.stdout ? err.stdout.toString() : '';
    if (output.includes('nothing to commit')) {
      console.log('Nothing to commit. Working tree is clean.');
    } else {
      fs.writeFileSync(commitFile, msg);
      console.error('Error: git commit failed. Message restored to docs/ai/commit.txt.');
      process.exit(1);
    }
  }
}
