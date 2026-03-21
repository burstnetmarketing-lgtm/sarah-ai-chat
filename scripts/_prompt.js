// scripts/_prompt.js
// Shared prompt helpers for interactive scripts.

function normalizeDigits(input) {
  return input
    .replace(/[۰-۹]/g, d => String(d.charCodeAt(0) - 1776))
    .replace(/[٠-٩]/g, d => String(d.charCodeAt(0) - 1632));
}

function askRunAgain(onYes) {
  process.stdout.write('\nRun again? 1) Yes  2) No: ');
  process.stdin.resume();
  process.stdin.setEncoding('utf8');
  process.stdin.setRawMode(true);
  process.stdin.once('data', key => {
    process.stdin.setRawMode(false);
    process.stdin.pause();
    if (key === '\u0003') process.exit(0);
    const digit = normalizeDigits(key);
    process.stdout.write(digit + '\n');
    if (digit === '1') onYes();
    else process.exit(0);
  });
}

module.exports = { askRunAgain };
