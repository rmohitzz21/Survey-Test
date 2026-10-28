# Architecture

How the portal’s PHP pages, JSON APIs, shared helpers, frontend assets, and authentication fit together.

## High-level request flow

```text
Browser
  │
  ├─ GET login.php  → Alpine loginApp() → POST api/login.php → session + csrf
  │                      └─ redirect → index.php
  │
  ├─ GET index.php
  │     require_auth → load_inquiries / load_accounts / … (PHP)
  │     HTML + Alpine x-data="spApp()" seeded with PHP <?= json_encode(...) ?>
  │     assets: app.css, dark-mode, mobile-polish, portal.css
  │     assets/js/fetch-session-guard.js (patches window.fetch)
  │     Alpine (CDN) + Flatpickr + DOMPurify + Chart.js
  │
  └─ fetch('api/….php') with X-CSRF-Token (spHeaders / window.__spCsrf)
        → api/*.php → includes/auth.php + db.php + functions.php
        → json_response([...])
```

## Portal page (`index.php`)

1. Starts output buffering, loads `includes/auth.php`, `db.php`, `functions.php`.
2. `sp_session_start()` then `require_auth('login.php')` — unauthenticated users get **302** to login.
3. Server-side data load: `load_inquiries($pdo, $user)`, `load_accounts`, follow-ups, warning counts, etc.
4. Renders one large HTML document. UI state lives in Alpine `spApp()` defined in an inline `<script>` near the bottom.
5. That script is **not** fully static JS: it embeds PHP (`ALL_STAGES`, `window.__spCsrf = …`, initial `inquiries`, `currentUser`, …).

Session expiry on API calls: `assets/js/fetch-session-guard.js` wraps `fetch`; if an `api/` response is **401**, the browser navigates to `login.php?expired=1`.

## Login (`login.php`)

- Alpine `loginApp()` posts JSON to `api/login.php`.
- On success, sets location to `index.php`.
- Signup requests go to `api/signup.php` (pending approval flow).

## API layer (`api/`)

Conventions used across endpoints:

| Concern | Typical pattern |
|---------|-----------------|
| Bootstrap | `require_once` auth + db (+ functions when needed); `sp_session_start()` |
| Auth | `require_auth_json()` or `require_staff_json()` / role checks |
| CSRF | `require_csrf($body)` on mutating POSTs |
| Body | `get_json_body()` |
| Response | `json_response(['ok' => true/false, …], $status)` |

Example: `api/ping.php` is a light poll used by the portal to refresh badges and CSRF (`csrf` field in JSON). `api/inquiry.php` dispatches on `$b['action']` (`stage_update`, `add_step`, `load_history`, …).

## Shared helpers (`includes/`)

- **`auth.php`**: `current_user()`, `is_admin()`, `is_master_admin()`, `csrf_token()`, `require_csrf()`, session cookie flags via `sp_is_https()`.
- **`db.php`**: environment gate. For `SP_APP_ENV=local`, DB is hard-wired to `localhost` / `sp_work` / `root` / empty password and **ignores** `SP_DB_*`. UAT/production require all four `SP_DB_*` values.
- **`functions.php`**: domain logic shared by pages and APIs (inquiry loading, closed/complete rules, leave notice window, upload checks, notifications).

## Frontend ↔ backend contract

| Mechanism | Detail |
|-----------|--------|
| Session cookie | Set on login; required for `index.php` and APIs |
| CSRF | `window.__spCsrf` from PHP or refreshed via login/ping JSON; sent as `X-CSRF-Token` |
| JSON APIs | `Content-Type: application/json` for most POSTs |
| Polling | `api/ping.php` drives refresh decisions in Alpine |
| Files | Upload through API helpers; download through `api/serve.php` when authenticated |

Do not bypass CSRF in new mutating endpoints. Do not serve upload binaries as static public files.

## Roles (simplified)

| Role | Notes |
|------|--------|
| Master Admin | Full admin + utilities such as `demo-data.php` / `fix-today-due.php` via `require_utility_master_admin` |
| Admin | Staff admin (accounts, leave decisions, etc.) |
| Member | Staff member workflows |
| Client | Restricted; many staff APIs use `require_staff_json()` |

Exact checks live in each API file and in `is_admin()` / `is_master_admin()`.

## Companion pages

`reports-view.php`, `teams-view.php`, `reminder.php`, `manage-user.php`, and `admin/index.php` are separate PHP entry points that share the same auth/DB stack. The primary day-to-day UI remains `index.php`.
