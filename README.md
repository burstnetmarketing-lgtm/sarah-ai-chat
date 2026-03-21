# Project Name Boilerplate

## Overview
This package is a reusable WordPress admin boilerplate for plugin projects that need an independent dashboard UI.

Folder layout:
- `ProjectName/project-name` => plugin source
- `ProjectName/docs` => architecture and usage docs

## UI Foundation
- The plugin ships with Mendy assets under `assets/mendy`.
- The dashboard shell is always rendered through a custom admin page.
- WordPress left sidebar and top bar are hidden on the shell page.

## Default Runtime Features
- A starter dashboard view (`project_view=dashboard`).
- A database-backed settings table.
- A database-backed menu table.
- A default seeded sidebar item: Dashboard.

## Getting Started

To rename this template for your project, ask your AI assistant:

> "Read `docs/setup-plan.md` and run the setup."

The AI will ask for your plugin name and slug, then refactor everything automatically.

## Extending For New Projects
1. Duplicate this package.
2. Run the setup as described above.
3. Add new domain modules and views.
4. Extend `MenuManagerPage` or replace it with your own workflow UI.
5. Keep business logic and UI rendering in separate classes.

## Notes
- This boilerplate is intentionally minimal.
- It is designed to start from one working dashboard and grow incrementally.
