// scripts/deploy.js
// ─────────────────────────────────────────────
// Deploys WordPress plugin to LocalWP
// Usage:
//   npm run deploy -- project-name  (deploy specific plugin)
//   npm run deploy                   (deploy all plugins)
// ─────────────────────────────────────────────

const path  = require('path');
const os    = require('os');
const fs    = require('fs');
const { spawn } = require('child_process');
const { buildAssets } = require('./_build');

const args    = process.argv.slice(2);
const cliSlug = args.filter(a => !a.startsWith('--'))[0];

// ── ANSI ────────────────────────────────────────
const C = {
  reset:  '\x1b[0m',
  bold:   '\x1b[1m',
  dim:    '\x1b[2m',
  green:  '\x1b[32m',
  yellow: '\x1b[33m',
  cyan:   '\x1b[36m',
  blue:   '\x1b[34m',
  red:    '\x1b[31m',
  gray:   '\x1b[90m',
  white:  '\x1b[97m',
};

const frames = ['⠋','⠙','⠹','⠸','⠼','⠴','⠦','⠧','⠇','⠏'];

// ── Column widths (dynamic based on terminal) ────
const DELAY   = 5; // ms between files
const W_TYPE  = 8;
const PADDING = 12; // borders + spaces overhead
const TERM_W  = process.stdout.columns || 120;
const REMAIN  = TERM_W - W_TYPE - PADDING - 6;
const W_NAME  = Math.floor(REMAIN * 0.38);
const W_DIR   = REMAIN - W_NAME;

// ── Helpers ─────────────────────────────────────
function trunc(str, len) {
  return str.length > len ? '…' + str.slice(-(len - 1)) : str.padEnd(len);
}

function fileType(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  const map = {
    '.php': 'PHP', '.js': 'JS', '.jsx': 'JSX', '.css': 'CSS',
    '.json': 'JSON', '.md': 'MD', '.txt': 'TXT',
    '.woff': 'FONT', '.woff2': 'FONT', '.ttf': 'FONT',
    '.png': 'IMG', '.jpg': 'IMG', '.svg': 'SVG', '.ico': 'IMG',
    '.map': 'MAP', '.lock': 'LOCK',
  };
  return (map[ext] || ext.replace('.', '').toUpperCase() || '—').padEnd(W_TYPE);
}

function typeColor(type) {
  const t = type.trim();
  if (t === 'PHP')  return C.blue;
  if (t === 'JS' || t === 'JSX') return C.yellow;
  if (t === 'CSS')  return C.cyan;
  if (t === 'FONT') return C.green;
  if (t === 'JSON') return '\x1b[35m'; // magenta
  return C.gray;
}


function spinner(frame) {
  return `${C.yellow}${frames[frame % frames.length]}${C.reset}`;
}

function tableHeader() {
  const h1 = C.bold + 'File'.padEnd(W_NAME) + C.reset;
  const h2 = C.bold + 'Directory'.padEnd(W_DIR) + C.reset;
  const h3 = C.bold + 'Type'.padEnd(W_TYPE) + C.reset;
  return [
    `  ${C.gray}┌${'─'.repeat(W_NAME + 2)}┬${'─'.repeat(W_DIR + 2)}┬${'─'.repeat(W_TYPE + 2)}┐${C.reset}`,
    `  ${C.gray}│${C.reset} ${h1} ${C.gray}│${C.reset} ${h2} ${C.gray}│${C.reset} ${h3} ${C.gray}│${C.reset}`,
    `  ${C.gray}├${'─'.repeat(W_NAME + 2)}┼${'─'.repeat(W_DIR + 2)}┼${'─'.repeat(W_TYPE + 2)}┤${C.reset}`,
  ];
}

function tableRow(relPath, status) {
  const normalized = relPath.replace(/\\/g, '/');
  const name       = path.basename(normalized);
  const dir        = path.dirname(normalized) === '.' ? '/' : path.dirname(normalized);
  const type       = fileType(normalized);
  const tc         = typeColor(type);

  const icon = status === 'done'
    ? `${C.green}✔${C.reset}`
    : `${C.cyan}→${C.reset}`;

  const nameCol = (status === 'done' ? C.dim : C.white) + trunc(name, W_NAME) + C.reset;
  const dirCol  = C.gray + trunc(dir, W_DIR) + C.reset;
  const typeCol = tc + type + C.reset;

  return `  ${C.gray}│${C.reset} ${icon} ${nameCol} ${C.gray}│${C.reset} ${dirCol} ${C.gray}│${C.reset} ${typeCol} ${C.gray}│${C.reset}`;
}

function tableFooter() {
  return [
    `  ${C.gray}└${'─'.repeat(W_NAME + 2)}┴${'─'.repeat(W_DIR + 2)}┴${'─'.repeat(W_TYPE + 2)}┘${C.reset}`,
  ];
}

// ── Ignore check ────────────────────────────────
function isIgnored(relativePath, patterns) {
  for (const pattern of patterns) {
    if (pattern.startsWith('*')) {
      if (relativePath.endsWith(pattern.slice(1))) return true;
    } else {
      if (relativePath === pattern ||
          relativePath.startsWith(pattern + path.sep) ||
          relativePath.includes(path.sep + pattern + path.sep)) return true;
    }
  }
  return false;
}


// ── Streaming table state ────────────────────────
let lastRowLine = 0;   // 1 if a "copying" row is on screen, else 0

function clearLastRow() {
  if (lastRowLine) {
    process.stdout.write('\x1b[1A\x1b[2K');
    lastRowLine = 0;
  }
}

function printRow(relPath, status) {
  clearLastRow();
  process.stdout.write(tableRow(relPath, status) + '\n');
  if (status === 'copying') lastRowLine = 1;
}

// ── Copy ─────────────────────────────────────────
let copyCount    = 0;
let copyPatterns = [];

function copyDir(src, dest, rel = '') {
  for (const item of fs.readdirSync(src)) {
    const relPath  = path.join(rel, item);
    if (isIgnored(relPath, copyPatterns)) continue;
    const srcPath  = path.join(src, item);
    const destPath = path.join(dest, item);
    if (fs.statSync(srcPath).isDirectory()) {
      fs.mkdirSync(destPath, { recursive: true });
      copyDir(srcPath, destPath, relPath);
    } else {
      printRow(relPath, 'copying');
      const end = Date.now() + DELAY;
      fs.copyFileSync(srcPath, destPath);
      while (Date.now() < end) {}
      copyCount++;
      clearLastRow();
      process.stdout.write(tableRow(relPath, 'done') + '\n');
    }
  }
}

// ── Load configs ─────────────────────────────────
const allConfigs = require('../docs/technical/deploy/deploy.config.js');

let configsToDeploy = [];
if (cliSlug) {
  if (!allConfigs[cliSlug]) {
    console.error(`${C.red}No config found for slug: "${cliSlug}"${C.reset}`);
    console.error('Available:', Object.keys(allConfigs).join(', '));
    process.exit(1);
  }
  configsToDeploy = [allConfigs[cliSlug]];
} else {
  configsToDeploy = Object.values(allConfigs);
}

const platform = os.platform();
let lastDestPath = null;

configsToDeploy.forEach(config => {
  const slug       = cliSlug || config.pluginSlug;
  const target     = config.targets[platform];
  const sourcePath = path.join(__dirname, '..', slug);

  console.log(`\n${C.bold}${C.cyan}▶  Deploying${C.reset}  ${C.bold}${slug}${C.reset}\n`);

  if (!target) {
    console.error(`${C.red}No deploy target for platform: "${platform}"${C.reset}`); process.exit(1);
  }
  if (!target.localWpPluginsPath?.trim()) {
    console.error(`${C.red}Invalid localWpPluginsPath for ${slug}${C.reset}`); process.exit(1);
  }
  if (!fs.existsSync(target.localWpPluginsPath)) {
    console.error(`${C.red}Path not found: ${target.localWpPluginsPath}${C.reset}`); process.exit(1);
  }
  if (!fs.existsSync(sourcePath)) {
    console.error(`${C.red}Source not found: ${sourcePath}${C.reset}`); process.exit(1);
  }

  const destPath = path.join(target.localWpPluginsPath, slug);

  // ── Step 1: Vite build ──
  buildAssets(sourcePath);

  // ── Step 2: Clean ──
  process.stdout.write(`  ${spinner(0)} Cleaning destination...`);
  if (fs.existsSync(destPath)) fs.rmSync(destPath, { recursive: true, force: true });
  fs.mkdirSync(destPath, { recursive: true });
  process.stdout.write(`\r  ${C.green}✔${C.reset} Destination ready\n\n`);

  // ── Step 3: Copy with streaming table ──
  copyPatterns = config.ignore;
  copyCount    = 0;
  lastRowLine  = 0;

  tableHeader().forEach(l => process.stdout.write(l + '\n'));

  copyDir(sourcePath, destPath);

  tableFooter().forEach(l => process.stdout.write(l + '\n'));

  console.log(`\n  ${C.green}${C.bold}✔  Done${C.reset}  ${C.bold}${copyCount}${C.reset} files  →  ${C.dim}${destPath}${C.reset}`);
  lastDestPath = destPath;
});

if (lastDestPath) {
  process.stdout.write('\nOpen deploy folder? 1) Yes  2) No: ');
  process.stdin.resume();
  process.stdin.setEncoding('utf8');
  process.stdin.setRawMode(true);
  process.stdin.once('data', key => {
    process.stdin.setRawMode(false);
    process.stdin.pause();
    if (key === '\u0003') process.exit(0);
    const digit = key
      .replace(/[۰-۹]/g, d => String(d.charCodeAt(0) - 1776))
      .replace(/[٠-٩]/g, d => String(d.charCodeAt(0) - 1632));
    process.stdout.write(digit + '\n');
    if (digit === '1') {
      const cmd = process.platform === 'win32'  ? 'explorer'
                : process.platform === 'darwin' ? 'open'
                : 'xdg-open';
      spawn(cmd, [lastDestPath], { stdio: 'ignore', detached: true }).unref();
    }
  });
}
