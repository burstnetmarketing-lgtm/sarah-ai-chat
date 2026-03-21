// scripts/zip.js
// ─────────────────────────────────────────────
// Builds a release ZIP for a plugin.
// Usage:
//   npm run zip                    → interactive menu + open folder prompt
//   npm run zip -- <slug>          → build specific plugin + open folder prompt
//   npm run zip -- <slug> --silent → build only, no prompts (used by publish.js)
//   npm run zip -- --all           → build all + open folder prompt
// ─────────────────────────────────────────────

const fs           = require('fs');
const path         = require('path');
const readline     = require('readline');
const { spawn, execSync } = require('child_process');
const { buildAssets } = require('./_build');

const ROOT         = path.join(__dirname, '..');
const ZIP_DIR      = path.join(ROOT, 'docs', 'technical', 'publish', 'zip');
const PROJECTS_DIR = path.join(ROOT, 'docs', 'technical', 'publish', 'projects');

const args     = process.argv.slice(2);
const buildAll = args.includes('--all');
const silent   = args.includes('--silent');
const cliSlug  = args.find(a => !a.startsWith('--'));

function normalizeDigits(input) {
  return input
    .replace(/[۰-۹]/g, d => String(d.charCodeAt(0) - 1776))
    .replace(/[٠-٩]/g, d => String(d.charCodeAt(0) - 1632));
}

// ── ANSI colors ─────────────────────────────────
const C = {
  reset:  '\x1b[0m',
  bold:   '\x1b[1m',
  dim:    '\x1b[2m',
  green:  '\x1b[32m',
  yellow: '\x1b[33m',
  cyan:   '\x1b[36m',
};

function sleep(ms) {
  const end = Date.now() + ms;
  while (Date.now() < end) {}
}

function drawBar(current, total, width = 26) {
  const pct  = total > 0 ? Math.min(Math.round((current / total) * 100), 100) : 100;
  const fill = total > 0 ? Math.round((current / total) * width) : width;
  const bar  = C.green + '█'.repeat(fill) + C.dim + '░'.repeat(width - fill) + C.reset;
  process.stdout.write(
    `\r  [${bar}] ${C.bold}${String(pct).padStart(3)}%${C.reset}  ${current}/${total} files`
  );
}

// ── Find plugins from projects/ config folder ───
function findPlugins() {
  return fs.readdirSync(PROJECTS_DIR)
    .filter(f => f.endsWith('.js'))
    .map(f => {
      const cfg = require(path.join(PROJECTS_DIR, f));
      return cfg.pluginSlug || path.basename(f, '.js');
    });
}

// ── Read version from plugin PHP header ────────
function readVersion(slug) {
  const phpFile = path.join(ROOT, slug, `${slug}.php`);
  const match   = fs.readFileSync(phpFile, 'utf8').match(/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)/m);
  if (!match) {
    console.error(`Could not find version in: ${phpFile}`);
    process.exit(1);
  }
  return match[1];
}

// ── Copy directory recursively ──────────────────
const COPY_IGNORE = [
  'node_modules', '.git', 'vendor', 'tests', '.DS_Store',
  'assets/src', 'vite.config.js', 'package.json', 'package-lock.json',
];

function countFiles(src) {
  let n = 0;
  for (const item of fs.readdirSync(src)) {
    if (COPY_IGNORE.includes(item)) continue;
    const p = path.join(src, item);
    n += fs.statSync(p).isDirectory() ? countFiles(p) : 1;
  }
  return n;
}

function copyDir(src, dest, counter, total) {
  fs.mkdirSync(dest, { recursive: true });
  for (const item of fs.readdirSync(src)) {
    if (COPY_IGNORE.includes(item)) continue;
    const srcPath  = path.join(src, item);
    const destPath = path.join(dest, item);
    if (fs.statSync(srcPath).isDirectory()) {
      copyDir(srcPath, destPath, counter, total);
    } else {
      fs.copyFileSync(srcPath, destPath);
      counter.count++;
      drawBar(counter.count, total);
    }
  }
}

// ── Write to log ────────────────────────────────
function writeLog(slug, lines) {
  const logFile = path.join(ROOT, 'docs', 'technical', 'publish', `${slug}-build.log`);
  fs.writeFileSync(logFile, lines.join('\n') + '\n', 'utf8');
}

// ── Build one plugin ────────────────────────────
function buildPlugin(slug) {
  const version       = readVersion(slug);
  const stamp         = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
  const zipName       = `${slug}-${version}-${stamp}.zip`;
  const zipPath       = path.join(ZIP_DIR, zipName);
  const stagingDir    = path.join(ROOT, `.staging-${stamp}`);
  const stagingPlugin = path.join(stagingDir, slug);
  const log           = [`Build started: ${new Date().toISOString()}`, `Slug: ${slug}`, `Version: ${version}`];

  console.log(`\n${C.bold}Building:${C.reset} ${slug} v${version}`);

  // ── Vite build ──
  buildAssets(path.join(ROOT, slug));

  // ── Copy phase ──
  const total   = countFiles(path.join(ROOT, slug));
  const counter = { count: 0 };
  copyDir(path.join(ROOT, slug), stagingPlugin, counter, total);
  sleep(300);
  process.stdout.write(
    `\r  ${C.green}✔${C.reset} Copied ${C.bold}${total}${C.reset} files.${''.padEnd(30)}\n`
  );
  log.push('Files staged.');

  // ── ZIP phase ──
  const frames = ['⠋','⠙','⠹','⠸','⠼','⠴','⠦','⠧','⠇','⠏'];
  process.stdout.write(`  ${C.yellow}⠋${C.reset} Creating ZIP...`);
  execSync(`tar -a -c -f "${zipPath}" -C "${stagingDir}" "${slug}"`, { stdio: 'pipe' });
  for (let i = 1; i <= 12; i++) {
    sleep(60);
    process.stdout.write(`\r  ${C.yellow}${frames[i % frames.length]}${C.reset} Creating ZIP...`);
  }
  sleep(100);
  process.stdout.write(`\r  ${C.green}✔${C.reset} ZIP created.              \n`);
  log.push(`ZIP created: ${zipName}`);

  fs.rmSync(stagingDir, { recursive: true, force: true });
  log.push(`Build complete: ${new Date().toISOString()}`);

  writeLog(slug, log);
  console.log(`ZIP ready : ${zipPath}`);
  return zipPath;
}

// ── Ask to open ZIP folder ──────────────────────
function askOpenFolder() {
  process.stdout.write('\nOpen ZIP folder? 1) Yes  2) No: ');
  process.stdin.resume();
  process.stdin.setEncoding('utf8');
  process.stdin.setRawMode(true);
  process.stdin.once('data', key => {
    process.stdin.setRawMode(false);
    process.stdin.pause();
    if (key === '\u0003') process.exit(0);
    const digit = normalizeDigits(key);
    process.stdout.write(digit + '\n');
    if (digit === '1') {
      const cmd = process.platform === 'win32'  ? 'explorer'
                : process.platform === 'darwin' ? 'open'
                : 'xdg-open';
      spawn(cmd, [ZIP_DIR], { stdio: 'ignore', detached: true }).unref();
    }
  });
}

// ── Main ────────────────────────────────────────
const plugins = findPlugins();

if (plugins.length === 0) {
  console.error('No plugins found in project root.');
  process.exit(1);
}

if (buildAll || plugins.length === 1) {
  (buildAll ? plugins : [plugins[0]]).forEach(buildPlugin);
  if (!silent) askOpenFolder();
} else if (cliSlug) {
  if (!plugins.includes(cliSlug)) {
    console.error(`Plugin not found: "${cliSlug}"`);
    console.error('Available:', plugins.join(', '));
    process.exit(1);
  }
  buildPlugin(cliSlug);
  if (!silent) askOpenFolder();
} else {
  process.stdin.resume();
  process.stdin.setEncoding('utf8');
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });

  console.log('\n=== Select plugin to ZIP ===\n');
  plugins.forEach((p, i) => console.log(`${i + 1}) ${p}`));
  console.log('0) All\n');

  rl.question('Select an option: ', answer => {
    rl.close();
    const choice = parseInt(normalizeDigits(answer.trim()), 10);
    if (choice === 0) {
      plugins.forEach(buildPlugin);
    } else if (choice >= 1 && choice <= plugins.length) {
      buildPlugin(plugins[choice - 1]);
    } else {
      console.log('Invalid option.');
      process.exit(1);
    }
    if (!silent) askOpenFolder();
  });
}
