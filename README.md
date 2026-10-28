# Survey Pacific — Work Journey

Internal inquiry and task workflow portal for Survey Pacific (IST). Staff manage client inquiries through stages and steps, plus leave, messages, calendar, reports, and related team tools.

## Stack

- **PHP** (XAMPP / Apache) with session auth and CSRF
- **MySQL** (local: `sp_work` when `SP_APP_ENV=local`)
- **Alpine.js** UI primarily in `index.php`
- **CSS** via `assets/app.css` (Tailwind build) plus `assets/css/portal.css` and related stylesheets

No Composer app framework and no root React/Vite app. Node is optional (Tailwind rebuild, JS unit scripts, Playwright).

## Quick start (local)

1. Place this project under your Apache document root.
2. Copy `.env.example` → `.env` and set `SP_APP_ENV=local`.
3. Ensure MySQL database `sp_work` exists and schema is applied.
4. Start Apache + MySQL; open `login.php` in the browser.

Details: **[docs/LOCAL_SETUP.md](docs/LOCAL_SETUP.md)**.

## Documentation

| Doc | Contents |
|-----|----------|
| [docs/PROJECT_OVERVIEW.md](docs/PROJECT_OVERVIEW.md) | Purpose, stack, capabilities |
| [docs/PROJECT_STRUCTURE.md](docs/PROJECT_STRUCTURE.md) | Where to find / change features |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Portal ↔ API ↔ auth flow |
| [docs/LOCAL_SETUP.md](docs/LOCAL_SETUP.md) | XAMPP, env vars (placeholders) |
| [docs/TESTING.md](docs/TESTING.md) | PHP, Node, Playwright commands |
| [docs/DEVELOPMENT_WORKFLOW.md](docs/DEVELOPMENT_WORKFLOW.md) | Branch, commit, PR |
| [docs/stitch.md](docs/stitch.md) | Design / Stitch prompt pack (reference only) |

## Tests (short)

```bat
php tests\due-ts-test.php
php tests\session-cookie-test.php
php tests\leave-notice-test.php
node tests\calendar-test.js

cd tests\browser
npm install
npx playwright install chromium
npx playwright test
```

Full commands and credential notes: **[docs/TESTING.md](docs/TESTING.md)**.

## Directory snapshot

| Path | Role |
|------|------|
| `index.php`, `login.php` | Main portal and login |
| `api/` | JSON APIs |
| `includes/` | Auth, DB, helpers (not web-served) |
| `assets/` | CSS/JS served to the browser |
| `admin/` | Master Admin tools UI |
| `tests/` | CLI tests + `tests/browser` Playwright |
| `docs/` | Developer documentation |
| `uploads/` / `storage/` | Files / sessions (not for Git commits) |

## Security notes

- Never commit `.env` or real passwords.
- Keep separate DB credentials for local / UAT / production.
- Mutating APIs expect CSRF (`X-CSRF-Token`); session expiry on `api/` 401 is handled by `assets/js/fetch-session-guard.js`.
- Rely on `.htaccess` (and Apache `AllowOverride`) to keep `/includes/`, `/scripts/`, `/tests/`, `/storage/`, and direct `/uploads/` closed.
