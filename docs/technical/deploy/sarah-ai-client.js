// deploy-configs/sarah-ai-client.js
// ─────────────────────────────────────────────
// Deploy config for the "sarah-ai-client" WordPress plugin.
// Edit the target paths below to match your local setup.
// ─────────────────────────────────────────────

module.exports = {
  pluginSlug: 'sarah-ai-client',

  // Files/folders to skip during deploy (relative to plugin root)
  ignore: [
    'node_modules',
    '.git',
    '*.map',
    'assets/src',
    'vite.config.js',
    'package.json',
    'package-lock.json',
  ],

  // Local WordPress plugins folder — edit manually per machine
  targets: {
    win32: {
      // Example: C:\\Users\\YourUser\\Local Sites\\mysite\\app\\public\\wp-content\\plugins
      localWpPluginsPath: 'C:\\Users\\Marketing\\Local Sites\\sarah\\app\\public\\wp-content\\plugins',
    },
    darwin: {
      // Example: /Users/youruser/Local Sites/mysite/app/public/wp-content/plugins
      localWpPluginsPath: '/Users/youruser/Local Sites/sarah-client/app/public/wp-content/plugins',
    },
  },
};
