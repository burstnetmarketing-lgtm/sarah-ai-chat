// scripts/update-project-name.js
// ─────────────────────────────────────────────
// Bumps the version in the project-name plugin's main PHP file.
// Usage:
//   npm run update:project-name [patch|minor|major]
//   Default versionType: minor
// ─────────────────────────────────────────────

const fs   = require('fs');
const path = require('path');

const VALID_TYPES = ['patch', 'minor', 'major'];

const pluginName  = 'project-name';
const versionType = process.argv[2] || 'minor';

if (!VALID_TYPES.includes(versionType)) {
  console.error(`Error: invalid versionType "${versionType}". Allowed: ${VALID_TYPES.join(', ')}`);
  process.exit(1);
}

const phpFile = path.join(__dirname, '..', pluginName, `${pluginName}.php`);

if (!fs.existsSync(phpFile)) {
  console.error(`Error: plugin file not found: ${phpFile}`);
  process.exit(1);
}

const content = fs.readFileSync(phpFile, 'utf8');

const versionMatch = content.match(/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)/m);
if (!versionMatch) {
  console.error('Error: could not find "Version: x.x.x" in plugin header.');
  process.exit(1);
}

const oldVersion = versionMatch[1];
const [major, minor, patch] = oldVersion.split('.').map(Number);

let newVersion;
if (versionType === 'major') newVersion = `${major + 1}.0.0`;
if (versionType === 'minor') newVersion = `${major}.${minor + 1}.0`;
if (versionType === 'patch') newVersion = `${major}.${minor}.${patch + 1}`;

const updated = content.replaceAll(oldVersion, newVersion);

fs.writeFileSync(phpFile, updated, 'utf8');

console.log(`Plugin  : ${pluginName}`);
console.log(`Version : ${oldVersion} → ${newVersion}`);
