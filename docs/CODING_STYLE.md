# Coding Style

---

## File Size

- Max 200 lines per file — if exceeded, split it
- One class per PHP file, one component per JSX file
- If a function exceeds 30 lines, extract parts of it

---

## SOLID Principles

**S — Single Responsibility**
Each class and function does one thing only.
A controller does not contain business logic. A service does not render UI.

**O — Open/Closed**
Extend behavior through new classes or hooks — do not modify existing working code to add features.

**L — Liskov Substitution**
Subclasses must be substitutable for their parent without breaking behavior.

**I — Interface Segregation**
Prefer small focused interfaces. A class should not implement methods it does not use.

**D — Dependency Inversion**
Inject dependencies — do not instantiate them inside the class.

```php
// ❌
class OrderService {
    public function process() {
        $mailer = new Mailer();
    }
}

// ✅
class OrderService {
    public function __construct(private MailerInterface $mailer) {}
}
```

---

## DRY — Don't Repeat Yourself

Every piece of logic exists in exactly one place.
If the same code appears twice, extract it into a function, class, or hook.

---

## YAGNI — You Aren't Gonna Need It

Do not write code for hypothetical future requirements.
Build what is needed now. Abstractions should emerge from real patterns, not speculation.

---

## Law of Demeter

Each object talks only to its immediate neighbors.

```php
// ❌
$user->getAddress()->getCity()->getName();

// ✅
$user->getCityName();
```

---

## Early Return

Avoid nested conditions. Exit early to keep the happy path flat.

```php
// ❌
if ($user) {
    if ($user->isActive()) {
        if ($user->hasPermission()) {
            // logic
        }
    }
}

// ✅
if (!$user) return;
if (!$user->isActive()) return;
if (!$user->hasPermission()) return;
// logic
```

---

## Naming

- Names must be self-explanatory — no abbreviations, no single letters
- If a comment is needed to explain a variable, rename the variable
- Booleans: prefix with `is`, `has`, `can` — `isActive`, `hasPermission`, `canPublish`
- Functions: verb + noun — `getUser`, `sendEmail`, `validateToken`
- Avoid generic names: `data`, `info`, `manager`, `handler`, `helper`, `util`

---

## Functions

- One function does one thing
- Max ~30 lines — if longer, split
- No side effects in functions that return a value
- No boolean parameters that change behavior — split into two functions

```php
// ❌
function getUser(bool $withPosts) { ... }

// ✅
function getUser() { ... }
function getUserWithPosts() { ... }
```

---

## PHP

- `declare(strict_types=1)` on every file
- Namespaces match folder structure: `PluginName\Admin\SettingsPage`
- No business logic in controllers — delegate to `Services/`
- No direct DB queries outside `DB/` layer
- Always use `$wpdb->prepare()` for queries with variables
- Use nonces for all form submissions
- Sanitize all input: `sanitize_text_field()`, `absint()`, etc.
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`
- Prefix all hooks and options: `plugin_name_`

**Naming**
- Classes: `PascalCase`
- Methods and functions: `camelCase`
- Variables: `snake_case`
- Constants: `UPPER_SNAKE_CASE`
- File names match class names: `ClassName.php`

---

## JavaScript / React

- One component per file, named to match its file
- No API calls inside components — use a custom hook or service layer
- Functional components only — no class components
- Always use `className`, never `class`
- No `useEffect` for data fetching — use a custom hook (`useUsers`, `useCredits`)
- Avoid prop drilling beyond 2 levels — use context

**Naming**
- Components: `PascalCase`
- Hooks: `camelCase` prefixed with `use` — `useAuth`, `useCredits`
- Functions and variables: `camelCase`
- Constants: `UPPER_SNAKE_CASE`

---

## Bootstrap / UI

- Follow `docs/UI_GUIDELINES.md` for all UI patterns
- No inline styles for layout — use Bootstrap utility classes
- No custom CSS if a Bootstrap class exists for the same purpose

---

## Logging

All errors and events must go through a single central logger — never use `console.error` or `error_log` directly in components or services.

**Default behavior:**
- Writes to a `.log` text file in the plugin root
- Also outputs to browser console
- To switch logging systems later, change only `utils/logger.js` and the PHP endpoint

**Frontend — `utils/logger.js`**

```js
const logger = {
  error: (context, message, data = {}) => {
    const entry = { level: 'error', context, message, data, time: new Date().toISOString() };
    console.error(`[${context}]`, message, data);
    fetch('/wp-json/plugin-slug/v1/log', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(entry),
    }).catch(() => {}); // never let logging break the app
  },
  warn: (context, message, data = {}) => {
    console.warn(`[${context}]`, message, data);
  },
  info: (context, message, data = {}) => {
    if (process.env.NODE_ENV === 'development') {
      console.info(`[${context}]`, message, data);
    }
  },
};

export default logger;
```

**Backend — PHP endpoint writes to log file**

```php
// Logs/Logger.php
class Logger {
    private static string $file;

    public static function init(): void {
        $dir = WP_CONTENT_DIR . '/logs';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        self::$file = $dir . '/plugin-name.log';
    }

    public static function write(string $level, string $context, string $message, array $data = []): void {
        $line = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $context,
            $message,
            $data ? json_encode($data) : ''
        );
        file_put_contents(self::$file, $line, FILE_APPEND | LOCK_EX);
    }
}
```

**Usage everywhere:**
```js
// ✅ frontend
logger.error('PaymentService', 'Payment failed', { userId, amount });

// ✅ backend
Logger::write('error', 'OrderService', 'Payment failed', ['user_id' => $userId]);
```

**Rules:**
- Never use `console.error`, `console.log`, or `error_log` directly — always go through the logger
- Always include context (class/component name), message, and relevant data
- Log file lives at `wp-content/logs/plugin-name.log` — outside the plugin, not publicly accessible
- Directory is created automatically by `Logger::init()` on plugin boot
- Log file is not in the repo — created at runtime on the server

---

## General

- No commented-out code in commits
- No dead code — remove it, git history preserves it
- No magic numbers — assign them to a named constant
- Fail loudly in development, fail gracefully in production
