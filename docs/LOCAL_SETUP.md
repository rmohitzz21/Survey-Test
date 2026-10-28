# Local setup (XAMPP)

Configure and run Survey Pacific on a **local** machine. Use placeholders only — never put real production passwords in docs or commits.

## Prerequisites

- XAMPP (or equivalent) with **Apache** + **MySQL** + **PHP** (PDO MySQL enabled)
- This project folder under the web root (example path used in this team’s local setup):

  `C:\xampp\htdocs\SurveyPacific\Survey_work_journey\Survey-test`

- A MySQL database named **`sp_work`** for local mode (schema applied via your team’s migrate / `db.sql` process)

## Environment file

1. Copy `.env.example` → `.env` in the project root.
2. Set:

```env
SP_APP_ENV=local
```

When `SP_APP_ENV=local`, `includes/db.php` connects to:

| Setting | Local value |
|---------|-------------|
| Host | `localhost` |
| Database | `sp_work` |
| User | `root` |
| Password | empty |

`SP_DB_HOST`, `SP_DB_NAME`, `SP_DB_USER`, and `SP_DB_PASS` are **ignored** in local mode (they still appear in `.env.example` for UAT/production templates).

3. **Never commit `.env`.**

### UAT / production (not for routine local work)

```env
SP_APP_ENV=uat
# or SP_APP_ENV=production
SP_DB_HOST=your_database_host
SP_DB_NAME=your_database_name
SP_DB_USER=your_database_user
SP_DB_PASS=change_me_to_a_strong_password
```

All four `SP_DB_*` values are required and must be non-empty.

Optional empty-database bootstrap keys (see `.env.example`): `SP_BOOTSTRAP_ADMIN_NAME`, `SP_BOOTSTRAP_ADMIN_EMAIL`, `SP_BOOTSTRAP_ADMIN_PASSWORD`. Use only on a brand-new empty accounts table; remove after the first Master Admin can log in.

## Application URL

Adjust to your Apache alias / folder layout. Example:

```text
http://localhost/SurveyPacific/Survey_work_journey/Survey-test/login.php
```

Portal (after login):

```text
http://localhost/SurveyPacific/Survey_work_journey/Survey-test/index.php
```

Unauthenticated `index.php` should redirect to `login.php`.

## First login

Use an existing local account created by your schema/seed process. Do not document real passwords here. If the accounts table is empty, follow your team’s bootstrap procedure (see comments in `.env.example`) — that is an ops step, not day-to-day development.

## Apache hardening (already in repo)

Root `.htaccess` is intended to deny public access to sensitive paths such as `/includes/`, `/scripts/`, `/tests/`, `/storage/`, `.env`, `*.sql`, and direct `/uploads/` listing. Ensure `AllowOverride` permits these rules.

## Optional: rebuild Tailwind

Only needed if you edit `src/input.css` / Tailwind class usage and want a fresh `assets/app.css`:

```bat
npx tailwindcss -i src/input.css -o assets/app.css --minify
```

Runtime does **not** require Node.

## Next steps

- [Architecture](ARCHITECTURE.md) — how login, portal, and APIs connect  
- [Testing](TESTING.md) — PHP / JS / Playwright commands  
- [Development workflow](DEVELOPMENT_WORKFLOW.md) — branches and PRs  
