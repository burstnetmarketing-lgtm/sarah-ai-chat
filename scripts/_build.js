// scripts/_build.js
// ─────────────────────────────────────────────
// Shared Vite build helper used by deploy.js and zip.js
// ─────────────────────────────────────────────

const fs         = require('fs');
const path       = require('path');
const { execSync } = require('child_process');

const C = {
  reset: '\x1b[0m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
};

const frames = ['⠋','⠙','⠹','⠸','⠼','⠴','⠦','⠧','⠇','⠏'];

/**
 * Runs `npm run build` inside pluginDir if a package.json exists.
 * Shows a spinner while building and elapsed time on completion.
 *
 * @param {string} pluginDir  Absolute path to the plugin root.
 * @returns {boolean}         True if a build was run, false if skipped.
 */
function buildAssets(pluginDir) {
  if (!fs.existsSync(path.join(pluginDir, 'package.json'))) {
    return false;
  }

  let frame = 0;
  process.stdout.write(`  ${C.yellow}${frames[0]}${C.reset} Compiling assets...`);
  const interval = setInterval(() => {
    process.stdout.write(`\r  ${C.yellow}${frames[++frame % frames.length]}${C.reset} Compiling assets...`);
  }, 80);

  const t0 = Date.now();
  try {
    execSync('npm run build', { cwd: pluginDir, stdio: 'pipe' });
  } finally {
    clearInterval(interval);
  }

  const elapsed = ((Date.now() - t0) / 1000).toFixed(1);
  process.stdout.write(
    `\r  ${C.green}✔${C.reset} Assets compiled     ${'\x1b[90m'}${elapsed}s${C.reset}\n`
  );

  return true;
}

module.exports = { buildAssets };
