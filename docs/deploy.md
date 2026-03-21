# Deploy System

## Status: Phase 2 — Full Deploy Implementation

The deploy system is now fully implemented. It copies your WordPress plugin files to your local LocalWP installation.

---

## File Overview

### `deploy-configs/deploy.config.js`
Holds all deploy configuration:
- `pluginSlug` — the WordPress plugin folder name
- `ignore` — list of files/folders to skip during deploy
- `targets.win32.localWpPluginsPath` — path to local WP plugins folder on Windows
- `targets.darwin.localWpPluginsPath` — path to local WP plugins folder on macOS

**You must edit this file manually** before running a deploy.
Do not commit real local paths if this repo is shared.

### `scripts/deploy.js`
The deploy entry point. Run via:
```
npm run deploy          # Deploy all plugins
npm run deploy -- slug  # Deploy specific plugin
```

It:
- Loads the config
- Detects the OS
- Validates paths
- Removes any existing plugin folder
- Copies plugin files recursively
- Respects ignore rules
- Prints detailed logs

### `package.json`
Defines the `deploy` npm script so you can run `npm run deploy` from the project root.

---

## How It Works

1. **Path Resolution**: Uses `targets.<platform>.localWpPluginsPath` + `pluginSlug` for destination
2. **Validation**: Checks that source and target base paths exist
3. **Cleanup**: Removes existing plugin folder at destination (safe, only the plugin folder)
4. **Copy**: Recursively copies all files except ignored ones
5. **Ignore Rules**: Supports exact names (`node_modules`) and extensions (`*.map`)

## Ignore Rules

The `ignore` array in config supports:
- Exact folder/file names: `node_modules`, `.git`
- File extensions: `*.map`, `*.log`

Files matching these patterns are skipped during copy.

## Editing Paths

For Windows: Edit `targets.win32.localWpPluginsPath` in your plugin's config file.
For macOS: Edit `targets.darwin.localWpPluginsPath`.

Example:
```js
targets: {
  win32: { localWpPluginsPath: 'C:\\Users\\YourName\\Local Sites\\site\\app\\public\\wp-content\\plugins' },
  darwin: { localWpPluginsPath: '/Users/YourName/Local Sites/site/app/public/wp-content/plugins' }
}
```

## Safety

- Only deletes within the resolved plugin destination folder
- Validates paths are not empty before operations
- Uses Node.js built-ins only, no external dependencies

---

## Running the Deploy

Three equivalent ways to run from the project root:

| Command | Shell |
|---|---|
| `npm run deploy` | Any |
| `.\deploy.ps1` | PowerShell |
| `.\deploy.bat` | Command Prompt / PowerShell |

### With a specific plugin slug

```
npm run deploy -- project-name
.\deploy.ps1 project-name
.\deploy.bat project-name
```

### First-time PowerShell setup

If PowerShell blocks `.\deploy.ps1` with an execution policy error, run once:
```powershell
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
```
