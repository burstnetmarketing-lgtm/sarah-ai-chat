// scripts/_publish-config.js
// ─────────────────────────────────────────────
// Loads publish configs, prompts for missing values, and saves back to file.
// Used by zip.js and publish.js.
// ─────────────────────────────────────────────

const fs       = require('fs');
const path     = require('path');
const readline = require('readline');

const PUBLISH_DIR  = path.join(__dirname, '..', 'docs', 'technical', 'publish');
const PROJECTS_DIR = path.join(PUBLISH_DIR, 'projects');

function normalizeDigits(input) {
  return input
    .replace(/[۰-۹]/g, d => String(d.charCodeAt(0) - 1776))
    .replace(/[٠-٩]/g, d => String(d.charCodeAt(0) - 1632));
}

function ask(rl, question) {
  return new Promise(resolve => rl.question(question, answer => resolve(answer.trim())));
}

function saveConfig(filePath, config) {
  const content =
    `// ${path.basename(filePath)}\n` +
    `// ${'─'.repeat(45)}\n` +
    `// Publish config for the ${config.pluginSlug} plugin.\n` +
    `// ${'─'.repeat(45)}\n\n` +
    `module.exports = {\n` +
    `  pluginSlug: '${config.pluginSlug}',\n` +
    `  projectGuid: '${config.projectGuid}',\n` +
    `};\n`;
  fs.writeFileSync(filePath, content, 'utf8');
}

async function loadConfigs() {
  const files = fs.readdirSync(PROJECTS_DIR)
    .filter(f => f.endsWith('.js'));

  const configs = [];

  for (const f of files) {
    const filePath = path.join(PROJECTS_DIR, f);
    // Clear require cache to get fresh values after save
    delete require.cache[require.resolve(filePath)];
    const config = { ...require(filePath), _file: filePath };
    configs.push(config);
  }

  return configs;
}

// requireGuid = false  →  used by zip.js  (slug only)
// requireGuid = true   →  used by publish.js (slug + guid)
async function ensureConfigs(configs, requireGuid = false) {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  process.stdin.setEncoding('utf8');

  for (const config of configs) {
    let changed = false;

    if (!config.pluginSlug) {
      config.pluginSlug = await ask(rl, `Plugin slug for ${path.basename(config._file, '.js')}: `);
      changed = true;
    }

    if (requireGuid && !config.projectGuid) {
      config.projectGuid = await ask(rl, `Project GUID for ${config.pluginSlug}: `);
      changed = true;
    }

    if (changed) {
      saveConfig(config._file, config);
      console.log(`Config saved: ${path.basename(config._file)}`);
    }
  }

  rl.close();
  return configs;
}

async function promptSelectConfig(configs, label = 'plugin') {
  if (configs.length === 1) return [configs[0]];

  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  process.stdin.setEncoding('utf8');

  console.log(`\n=== Select ${label} ===\n`);
  configs.forEach((c, i) => console.log(`${i + 1}) ${c.pluginSlug}`));
  console.log('0) All\n');

  const answer = await ask(rl, 'Select an option: ');
  rl.close();

  const choice = parseInt(normalizeDigits(answer), 10);
  if (choice === 0) return configs;
  if (choice >= 1 && choice <= configs.length) return [configs[choice - 1]];

  console.log('Invalid option.');
  process.exit(1);
}

module.exports = { loadConfigs, ensureConfigs, promptSelectConfig, normalizeDigits };

