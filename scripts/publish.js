// scripts/publish.js
// ─────────────────────────────────────────────
// Uploads and publishes the latest release ZIP for a plugin.
// Usage:
//   npm run publish -- <slug>
//   npm run publish -- <slug> --zip   (zip first, then publish)
//   npm run publish -- --all
//   (no args) → interactive menu
// ─────────────────────────────────────────────

const fs       = require('fs');
const path     = require('path');
const { execSync } = require('child_process');
const { loadConfigs, ensureConfigs, promptSelectConfig } = require('./_publish-config');

const ROOT        = path.join(__dirname, '..');
const PUBLISH_DIR = path.join(ROOT, 'docs', 'technical', 'publish');
const ZIP_DIR     = path.join(PUBLISH_DIR, 'zip');
const AUTH_FILE   = path.join(PUBLISH_DIR, 'auth.js');

const args       = process.argv.slice(2);
const publishAll = args.includes('--all');
const zipFirst   = args.includes('--zip');
const cliSlug    = args.find(a => !a.startsWith('--'));

// ── Load auth ───────────────────────────────────
if (!fs.existsSync(AUTH_FILE)) {
  console.error('Error: auth.js not found. Create docs/technical/publish/auth.js with apiKey and baseUrl.');
  process.exit(1);
}
const auth = require(AUTH_FILE);

if (!auth.apiKey || auth.apiKey === 'YOUR_API_KEY_HERE') {
  console.error('Error: apiKey is not set in auth.js.');
  process.exit(1);
}
if (!auth.baseUrl || auth.baseUrl === 'https://your-update-server.com/') {
  console.error('Error: baseUrl is not set in auth.js.');
  process.exit(1);
}

// ── Find latest ZIP for slug ────────────────────
function findLatestZip(slug) {
  const zips = fs.readdirSync(ZIP_DIR)
    .filter(f => f.startsWith(`${slug}-`) && f.endsWith('.zip'))
    .map(f => ({ name: f, mtime: fs.statSync(path.join(ZIP_DIR, f)).mtime }))
    .sort((a, b) => b.mtime - a.mtime);
  if (zips.length === 0) return null;
  return path.join(ZIP_DIR, zips[0].name);
}

// ── Write to log ────────────────────────────────
function writeLog(slug, lines) {
  const logFile = path.join(PUBLISH_DIR, `${slug}-publish.log`);
  fs.writeFileSync(logFile, lines.join('\n') + '\n', 'utf8');
}

// ── Upload ZIP ──────────────────────────────────
async function upload(slug, guid, zipPath) {
  const uploadUrl = `${auth.baseUrl}wp-json/plugin-management/v1/upload-release`;
  const formData  = new FormData();
  formData.append('release_zip', new Blob([fs.readFileSync(zipPath)]), path.basename(zipPath));

  const res  = await fetch(uploadUrl, {
    method: 'POST',
    headers: {
      'X-PM-Project-Slug': slug,
      'X-PM-Project-Guid': guid,
      'X-PM-Security-Key': auth.apiKey,
    },
    body: formData,
  });

  const json = await res.json();
  if (!json.success || !json.release_id) {
    throw new Error(`Upload failed: ${json.message || JSON.stringify(json)}`);
  }
  return json.release_id;
}

// ── Publish release ─────────────────────────────
async function publishRelease(slug, guid, releaseId) {
  const publishUrl = `${auth.baseUrl}wp-json/plugin-management/v1/publish-release`;
  const res  = await fetch(publishUrl, {
    method: 'POST',
    headers: {
      'X-PM-Project-Slug': slug,
      'X-PM-Project-Guid': guid,
      'X-PM-Security-Key': auth.apiKey,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `release_id=${releaseId}`,
  });

  const json = await res.json();
  if (!json.success) {
    throw new Error(`Publish failed: ${json.message || JSON.stringify(json)}`);
  }
}

// ── Publish one plugin ──────────────────────────
async function publishPlugin(config) {
  const { pluginSlug, projectGuid } = config;
  const remoteSlug = config.remoteSlug || pluginSlug;
  const log = [`Publish started: ${new Date().toISOString()}`, `Slug: ${pluginSlug}`, `Remote slug: ${remoteSlug}`];

  if (!projectGuid) {
    console.error(`Error: projectGuid is not set for "${pluginSlug}". Run publish again to enter it.`);
    process.exit(1);
  }

  if (zipFirst) {
    console.log(`\nZipping ${pluginSlug} first...`);
    execSync(`node ${path.join(__dirname, 'zip.js')} ${pluginSlug} --silent`, { stdio: 'inherit' });
  }

  const zipPath = findLatestZip(pluginSlug);
  if (!zipPath) {
    console.error(`No ZIP found for "${pluginSlug}". Run: npm run zip -- ${pluginSlug}`);
    process.exit(1);
  }

  log.push(`ZIP: ${path.basename(zipPath)}`);
  console.log(`\nPublishing : ${pluginSlug}${remoteSlug !== pluginSlug ? ` → ${remoteSlug}` : ''}`);
  console.log(`ZIP        : ${path.basename(zipPath)}`);

  console.log('Uploading...');
  const releaseId = await upload(remoteSlug, projectGuid, zipPath);
  log.push(`Uploaded. release_id: ${releaseId}`);
  console.log(`Uploaded. release_id: ${releaseId}`);

  console.log('Publishing...');
  await publishRelease(remoteSlug, projectGuid, releaseId);
  log.push('Published successfully.');
  log.push(`Done: ${new Date().toISOString()}`);

  writeLog(pluginSlug, log);
  console.log(`Done: ${pluginSlug} published successfully.`);
}

// ── Main ────────────────────────────────────────
(async () => {
  // 1. Load configs (slugs only — no GUID prompts yet)
  const all = await loadConfigs();

  // 2. Select which plugin(s) to publish
  let selected;
  if (publishAll) {
    selected = all;
  } else if (cliSlug) {
    const cfg = all.find(c => c.pluginSlug === cliSlug);
    if (!cfg) {
      console.error(`No config found for slug: "${cliSlug}"`);
      process.exit(1);
    }
    selected = [cfg];
  } else {
    selected = await promptSelectConfig(all, 'plugin to publish');
  }

  // 3. Ensure GUID only for the selected plugin(s)
  selected = await ensureConfigs(selected, true);

  for (const config of selected) {
    await publishPlugin(config);
  }
})();
