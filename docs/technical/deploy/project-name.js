// deploy-configs/project-name.js
// ─────────────────────────────────────────────
// Config for the "project-name" WordPress plugin.
// Edit the target paths below to match your local setup.
// ─────────────────────────────────────────────

module.exports = {
  pluginSlug: 'project-name',

  // Files/folders to skip during deploy (relative to plugin root)
  // TODO (phase 2): populate as needed
  ignore: [
    'node_modules',
    '.git',
    '*.map',
    'assets/src',
    'vite.config.js',
    'package.json',
    'package-lock.json',
  ],

  // Local WordPress plugins folder — edit manually per OS
  targets: {
    win32: {
      // Example: C:\\Users\\YourUser\\Local Sites\\mysite\\app\\public\\wp-content\\plugins
      localWpPluginsPath: 'C:\\Users\\Marketing\\Local Sites\\template\\app\\public\\wp-content\\plugins',
    },
    darwin: {
      // Example: /Users/youruser/Local Sites/mysite/app/public/wp-content/plugins
      localWpPluginsPath: '/Users/youruser/Local Sites/mysite/app/public/wp-content/plugins',
    },
  },
};
