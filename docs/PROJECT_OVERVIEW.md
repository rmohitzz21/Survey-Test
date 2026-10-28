# Project overview

## Purpose

**Survey Pacific Work Journey** is an internal staff portal for managing client **inquiries** through stages and team tasks. It also covers follow-ups/reminders, leave, direct messages, announcements, warnings, shared resources, private notes, calendar/holidays, and reporting views.

Clients may have limited accounts; most workflows are staff-oriented (Member, Admin, Master Admin).

## Stack

| Layer | Technology |
|-------|------------|
| Server | PHP on Apache (typically XAMPP locally) |
| Data | MySQL via PDO (`includes/db.php`) |
| Auth | PHP sessions + CSRF tokens (`includes/auth.php`) |
| UI | Alpine.js SPA-style shell inside `index.php` |
| Styles | Tailwind build → `assets/app.css`, plus `assets/css/portal.css`, `assets/dark-mode.css`, `assets/mobile-polish.css` |
| Charts | Chart.js under `assets/vendor/` |

There is **no** Composer app framework and **no** React/Vite app in this repository. Node is optional (Tailwind rebuild, some JS unit tests, Playwright under `tests/browser/`).

## Main capabilities

- Inquiry lifecycle: create, list, stage updates, steps/tasks, attachments, remarks, closure
- Role-based access (Master Admin, Admin, Member, Client)
- Dashboard stats, My Tasks, calendar, reminders/follow-ups
- Leave requests and admin decisions
- Messages, announcements, notifications
- Teams directory, warnings, shared resources, private call notes
- Reports view (`reports-view.php`) and related portal Reports UI
- Authenticated file download via `api/serve.php` (direct `/uploads/` browsing denied)

## Related docs

- [Project structure](PROJECT_STRUCTURE.md)
- [Architecture](ARCHITECTURE.md)
- [Local setup](LOCAL_SETUP.md)
- [Testing](TESTING.md)
- [Development workflow](DEVELOPMENT_WORKFLOW.md)
