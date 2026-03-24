// scripts/update-sarah-ai-client.js
// ─────────────────────────────────────────────
// Bumps the version in the sarah-ai-client plugin.
// Updates package.json (source of truth) and syncs PHP header.
// Usage:
//   npm run update:client [patch|minor|major]
//   Default versionType: patch
// ─────────────────────────────────────────────

const fs   = require('fs');
const path = require('path');

const VALID_TYPES = ['patch', 'minor', 'major'];

const pluginName  = 'sarah-ai-client';
const versionType = process.argv[2] || 'patch';

if (!VALID_TYPES.includes(versionType)) {
  console.error(`Error: invalid versionType "${versionType}". Allowed: ${VALID_TYPES.join(', ')}`);
  process.exit(1);
}

const pluginDir = path.join(__dirname, '..', pluginName);
const pkgFile   = path.join(pluginDir, 'package.json');
const phpFile   = path.join(pluginDir, `${pluginName}.php`);

if (!fs.existsSync(pkgFile)) {
  console.error(`Error: package.json not found: ${pkgFile}`);
  process.exit(1);
}
if (!fs.existsSync(phpFile)) {
  console.error(`Error: plugin file not found: ${phpFile}`);
  process.exit(1);
}

// Read version from package.json (source of truth)
const pkg        = JSON.parse(fs.readFileSync(pkgFile, 'utf8'));
const oldVersion = pkg.version;
if (!oldVersion) {
  console.error('Error: could not find "version" in package.json.');
  process.exit(1);
}

const [major, minor, patch] = oldVersion.split('.').map(Number);
let newVersion;
if (versionType === 'major') newVersion = `${major + 1}.0.0`;
if (versionType === 'minor') newVersion = `${major}.${minor + 1}.0`;
if (versionType === 'patch') newVersion = `${major}.${minor}.${patch + 1}`;

// Update package.json
pkg.version = newVersion;
fs.writeFileSync(pkgFile, JSON.stringify(pkg, null, 2) + '\n', 'utf8');

// Sync PHP header
const phpContent = fs.readFileSync(phpFile, 'utf8');
const phpMatch   = phpContent.match(/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)/m);
if (!phpMatch) {
  console.error('Error: could not find "Version: x.x.x" in PHP header.');
  process.exit(1);
}
fs.writeFileSync(phpFile, phpContent.replace(phpMatch[1], newVersion), 'utf8');

console.log(`Plugin  : ${pluginName}`);
console.log(`Version : ${oldVersion} → ${newVersion}`);
