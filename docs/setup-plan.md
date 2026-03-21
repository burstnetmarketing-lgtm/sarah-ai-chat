# Setup Instructions

This file contains instructions for the AI to refactor this template into a real project.

---

## How to Use

The developer will ask you to read this file and run the setup.
Ask the developer for the following before doing anything:

```
Please provide:
1. Plugin display name  (e.g. My Awesome Plugin)
2. Plugin slug          (e.g. my-awesome-plugin)
```

Wait for the answer, then proceed with the steps below.

---

## Refactor Steps

Perform all steps in order. Do not skip any.

### 1. Rename plugin folder and main file
- `project-name/` → `{slug}/`
- `project-name/project-name.php` → `{slug}/{slug}.php`

### 2. Update plugin header in `{slug}/{slug}.php`
- `Plugin Name: Project Name` → `Plugin Name: {display name}`

### 3. Rename update script
- `scripts/update-project-name.js` → `scripts/update-{slug}.js`
- Inside the file: change `pluginName = 'project-name'` → `pluginName = '{slug}'`

### 4. Update `package.json`
- `"name"` → `"{slug}"`
- `"update:project-name"` → `"update:{slug}"`
- Script value → `"node scripts/update-{slug}.js"`

### 5. Rename and update deploy config
- `docs/technical/deploy/project-name.js` → `docs/technical/deploy/{slug}.js`
- Inside the file: change `pluginSlug: 'project-name'` → `pluginSlug: '{slug}'`

---

## After Setup

When all steps are done:
1. Reset `docs/ai/task-counter.txt` to `1000`
2. Clear `docs/updates.md` completely
3. Create the first task file at `docs/tasks/0.1.0/task-1000-initialize-project.md`
4. Append to `docs/updates.md`:
   ```
   1000 Initialized project as {display name}.
   ```
5. Write to `docs/ai/commit.txt`:
   ```
   1000 Initialized project as {display name}.
   ```
6. Tell the developer: "Setup complete. Run `npm run commit` to save the initial state."
