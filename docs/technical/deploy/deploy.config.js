// deploy-configs/deploy.config.js
// ─────────────────────────────────────────────
// Aggregator — automatically loads every slug.js in this folder.
// To add a new plugin: create deploy-configs/<slug>.js
// No manual registration needed.
// ─────────────────────────────────────────────

const fs   = require('fs');
const path = require('path');

const configs = {};

fs.readdirSync(__dirname)
  .filter(f => f.endsWith('.js') && f !== 'deploy.config.js')
  .forEach(f => {
    const slug = path.basename(f, '.js');
    configs[slug] = require(path.join(__dirname, f));
  });

module.exports = configs;
