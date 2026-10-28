# Project structure

Annotated map of the **tracked** application. Prefer this over guessing from folder names alone.

## Directory tree (high level)

```text
Survey-test/
├── index.php              # Authenticated portal shell (PHP seed + Alpine UI)
├── login.php              # Login / signup request UI
├── manage-user.php        # User/account management UI (admin flows)
├── reminder.php           # Reminder-oriented companion page
├── reports-view.php       # Standalone reports page
├── teams-view.php         # Standalone teams page
├── demo-data.php          # Master Admin utility (demo seed/remove) — guarded
├── fix-today-due.php      # Master Admin utility (due-date repair) — guarded
├── migrate.php            # DB migrate entry (ops; treat carefully)
├── db.sql                 # Schema reference dump
├── .env.example           # Env template (placeholders only)
├── .htaccess              # Apache deny rules for sensitive paths
├── api/                   # JSON HTTP API endpoints
├── includes/              # Shared PHP (auth, db, helpers) — not public
├── admin/                 # Master Admin inquiry tools UI
├── assets/
│   ├── app.css            # Built Tailwind CSS
│   ├── dark-mode.css
│   ├── mobile-polish.css
│   ├── css/portal.css     # Extracted portal chrome / calendar / Flatpickr skin
│   ├── js/fetch-session-guard.js  # 401 → login.php?expired=1 for api/ fetch
│   └── vendor/chart.umd.min.js
├── src/input.css          # Tailwind source (optional rebuild)
├── uploads/               # User files (gitignored binaries; HTTP listing denied)
├── storage/               # Session files (gitignored)
├── scripts/               # CLI / quarantined maintenance (HTTP denied)
├── tests/                 # PHP/JS CLI tests + tests/browser Playwright
└── docs/                  # Documentation (this folder)
```

## Where to change common features

| I want to… | Start here |
|------------|------------|
| Change portal layout / Alpine views | `index.php` (large single file); styles in `assets/css/portal.css`, `assets/mobile-polish.css`, `assets/dark-mode.css` |
| Change login UX | `login.php` |
| Change inquiry list/create API | `api/inquiries.php` |
| Change stage/steps/remarks/close | `api/inquiry.php` + helpers in `includes/functions.php` (`load_inquiries`, `is_closed_inquiry`, …) |
| Change leave rules | `api/leave.php` + leave helpers in `includes/functions.php` (`leave_has_notice`, `LEAVE_*`) |
| Change auth / CSRF / session cookie | `includes/auth.php` |
| Change DB connection / env rules | `includes/db.php`, `includes/env.php`, `.env.example` |
| Change notifications content | `notify_user()` in `includes/functions.php` and call sites in APIs |
| Change reports page | `reports-view.php` (+ Reports section inside `index.php`) |
| Change teams page | `teams-view.php`, `api/teams.php` |
| Add/adjust CLI tests | `tests/*.php`, `tests/*.js` |
| Add browser smoke coverage | `tests/browser/smoke.spec.js` |
| Master Admin demo/repair tools | `demo-data.php`, `fix-today-due.php` (Master Admin + CSRF; demo refuses known prod DB name) |

## `api/` endpoints (tracked)

| File | Typical role |
|------|----------------|
| `login.php` / `logout.php` / `signup.php` | Auth session |
| `ping.php` | Lightweight poll + CSRF refresh |
| `inquiries.php` / `inquiry.php` | Inquiry collection vs single-inquiry actions |
| `followup.php` | Follow-ups / reminders data |
| `leave.php` | Leave list / request / decide / cancel |
| `accounts.php` | Account admin (approve, roles, …) |
| `messages.php` / `announcements.php` / `notifications.php` | Comms |
| `holidays.php` | Calendar holidays |
| `teams.php` / `warnings.php` / `notes.php` / `resources.php` | Team tools |
| `categories.php` | Project categories |
| `upload.php` / `serve.php` | Upload validation / authenticated download |

## `includes/`

| File | Role |
|------|------|
| `auth.php` | Sessions, `require_auth` / `require_auth_json`, roles, CSRF, `json_response` |
| `db.php` | Loads env; `SP_APP_ENV=local` → fixed `localhost`/`sp_work`; else requires `SP_DB_*` |
| `env.php` | `.env` loading |
| `functions.php` | Domain helpers (inquiries, leave, slots, uploads, …) |
| `.htaccess` | Deny web access |

## `admin/`

| Path | Role |
|------|------|
| `admin/index.php` | Master Admin–oriented inquiry tooling UI |
| `admin/.htaccess` | Directory rules |

## `tests/` and `tests/browser/`

- Root `tests/*.php` / `*.js` — CLI unit/smoke and `feature-integration-test.php` (needs local Apache + MySQL).
- `tests/browser/` — **isolated** Playwright package (own `package.json`); does not use a root React/Vite app (there is none).

## `docs/`

| File | Role |
|------|------|
| `stitch.md` | Design / Stitch prompt pack (reference only; not runtime) |
| `PROJECT_*.md`, `ARCHITECTURE.md`, … | Developer handover docs |

## Local-only files you may see

Working trees sometimes contain **uncommitted** migrate/bootstrap helpers (e.g. `update-live-db.php`, `includes/schema-update.php`, `live-db-*.sql`). Treat them as held ops artifacts: do not assume they are part of the published app contract, and do not commit them unless your team explicitly decides to.
