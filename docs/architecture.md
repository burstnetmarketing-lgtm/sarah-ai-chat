# Plugin Architecture

## Overview

_Briefly describe what this plugin does and its role in the system._

---

## Folder Structure

```
plugin-name/
├── plugin-name.php        ← entry point, constants, bootstrap
├── includes/
│   ├── Core/              ← boot, activation, deactivation
│   ├── Admin/             ← admin pages, menus
│   ├── Api/               ← REST API endpoints
│   ├── DB/                ← database schema, migrations
│   └── Services/          ← business logic (no UI, no DB direct calls)
├── assets/
│   ├── src/               ← React + Bootstrap source
│   └── dist/              ← compiled output (enqueued by WordPress)
└── templates/             ← PHP templates if needed
```

---

## Module Responsibilities

| Module | Responsibility |
|---|---|
| `Core/` | Plugin lifecycle — activation, deactivation, constants |
| `Admin/` | Register menus, enqueue assets, render app shell |
| `Api/` | REST API endpoints — validation, response format |
| `DB/` | Table creation, schema versioning |
| `Services/` | Business logic — no direct HTTP or DB calls |
| `assets/src/` | React SPA — all UI lives here |

---

## Boot Flow

1. `plugin-name.php` defines constants and requires autoloader
2. On `plugins_loaded` — Core bootstraps Admin and API
3. On `admin_menu` — Admin registers menu pages
4. On `admin_init` — if plugin page requested, output SPA shell and `exit`
5. REST API endpoints register on `rest_api_init`

---

## Admin Page Pattern

Plugin pages output a full HTML page and exit before WordPress renders its chrome.
WordPress header, sidebar, and footer are never shown on plugin pages.

```php
add_action('admin_init', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'plugin-slug') {
        wp_enqueue_script('plugin-app', plugin_dir_url(__FILE__) . 'assets/dist/app.js', [], null, true);
        wp_enqueue_style('plugin-app', plugin_dir_url(__FILE__) . 'assets/dist/app.css');
        ?><!DOCTYPE html>
        <html>
        <head><?php wp_head(); ?></head>
        <body><div id="app"></div><?php wp_footer(); ?></body>
        </html><?php
        exit;
    }
});
```

---

## REST API Pattern

```php
register_rest_route('plugin-slug/v1', '/resource', [
    'methods'             => 'GET',
    'callback'            => [ResourceController::class, 'index'],
    'permission_callback' => [Auth::class, 'verify'],
]);
```

- All endpoints under `/wp-json/plugin-slug/v1/`
- Authentication via header or JWT
- All responses: `{ success, data, message }`

---

## Database Tables

_List custom tables used by this plugin._

| Table | Purpose |
|---|---|
| `wp_plugin_` | _describe_ |

---

## Data Flow

```
React (assets/src)
  → fetch /wp-json/plugin-slug/v1/...
  → Api/      validates request + auth
  → Services/ business logic
  → DB/       read / write
  → JSON response → React
```
