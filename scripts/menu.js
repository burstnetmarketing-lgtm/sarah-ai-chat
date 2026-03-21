const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

const scriptsDir = __dirname;

function normalizeDigits(input) {
  return input
    .replace(/[۰-۹]/g, d => String(d.charCodeAt(0) - 1776))
    .replace(/[٠-٩]/g, d => String(d.charCodeAt(0) - 1632));
}

function formatName(file) {
  return file
    .replace('.js', '')
    .replace(/-/g, ' ')
    .replace(/\b\w/g, c => c.toUpperCase());
}

function getScripts() {
  return fs
    .readdirSync(scriptsDir)
    .filter(file =>
      file.endsWith('.js') &&
      file !== 'menu.js' &&
      !file.startsWith('_')
    )
    .sort();
}

function readKey(prompt, callback) {
  process.stdout.write(prompt);
  process.stdin.setEncoding('utf8');
  process.stdin.setRawMode(true);
  process.stdin.resume();
  process.stdin.once('data', key => {
    process.stdin.setRawMode(false);
    process.stdin.pause();
    callback(key);
  });
}

function runScript(file) {
  const rootDir = path.join(scriptsDir, '..');
  spawn('cmd', ['/c', 'start', '/MAX', 'cmd', '/k', `node scripts\\${file}`], {
    cwd: rootDir,
    shell: false,
    detached: true,
    stdio: 'ignore'
  }).unref();
}

function showMenu() {
  console.clear();

  const files = getScripts();

  console.log('=== SCRIPT MENU ===\n');
  files.forEach((file, index) => {
    console.log(`${index + 1}) ${formatName(file)}`);
  });
  console.log('0) Exit\n');

  readKey('Select an option: ', key => {
    if (key === '\u0003') process.exit(0); // Ctrl+C

    const digit = normalizeDigits(key).replace(/[^\d]/g, '');
    const choice = parseInt(digit, 10);

    process.stdout.write(digit + '\n');

    if (choice === 0) {
      console.log('\nBye');
      process.exit(0);
    }

    if (!isNaN(choice) && choice > 0 && choice <= files.length) {
      runScript(files[choice - 1]);
    } else {
      console.log('\nInvalid option');
    }

    showMenu();
  });
}

showMenu();
