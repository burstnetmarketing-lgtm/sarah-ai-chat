const fs = require('fs');
const path = require('path');
const os = require('os');
const { spawn, execFileSync } = require('child_process');

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
  if (os.platform() === 'win32') {
    spawn('cmd', ['/c', 'start', '/MAX', 'cmd', '/k', `node scripts\\${file}`], {
      cwd: rootDir,
      shell: false,
      detached: true,
      stdio: 'ignore',
    }).unref();
  } else {
    // On macOS/Linux: run inline in the same terminal, wait for completion
    try {
      execFileSync(process.execPath, [path.join('scripts', file)], {
        cwd: rootDir,
        stdio: 'inherit',
      });
    } catch {
      // script exited with non-zero — already printed its own error
    }
  }
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
