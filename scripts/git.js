// scripts/git.js
const { execSync } = require('child_process');

function normalizeDigits(input) {
  return input
    .replace(/[۰-۹]/g, d => String(d.charCodeAt(0) - 1776))
    .replace(/[٠-٩]/g, d => String(d.charCodeAt(0) - 1632));
}

console.log('\n1) Pull\n2) Push\n');
process.stdout.write('Select: ');

process.stdin.resume();
process.stdin.setEncoding('utf8');
process.stdin.setRawMode(true);

process.stdin.once('data', key => {
  process.stdin.setRawMode(false);
  process.stdin.pause();

  const choice = normalizeDigits(key);
  process.stdout.write(choice + '\n');

  if (key === '\u0003') process.exit(0);

  if (choice === '1') {
    execSync('git pull', { stdio: 'inherit' });
  } else if (choice === '2') {
    execSync('git push', { stdio: 'inherit' });
  } else {
    console.log('Invalid option.');
    process.exit(1);
  }
});
