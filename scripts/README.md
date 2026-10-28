# scripts/ — off-webroot quarantine

Apache denies HTTP access to this directory (see `.htaccess`).

Use for **one-off** or **sensitive** maintenance scripts that must not sit in the public document root.

## Currently quarantined

| File | Why |
|------|-----|
| `bootstrap-first-admin.php` | CLI-only fresh-install schema + first Master Admin |
| `migrate-shalini-password.php` | User-specific password reset (historical) — **gitignored** |
| `migrate-shalini-password.sql` | Companion SQL — **gitignored** |
| `fix-b64.php` | One-off base64 column repair |

## Fresh install — first Master Admin (CLI only)

Web `migrate.php` / `update-live-db.php` require an existing Master Admin session, so a brand-new empty database cannot use them. Use this CLI once:

1. Configure `.env`: `SP_APP_ENV`, database settings (`SP_DB_*` when not `local`), and one-time `SP_BOOTSTRAP_ADMIN_EMAIL` / `SP_BOOTSTRAP_ADMIN_PASSWORD` (optional `SP_BOOTSTRAP_ADMIN_NAME`). See `.env.example`.
2. Back up the intended database.
3. From the project root:

```bat
php scripts\bootstrap-first-admin.php --confirm=YOUR_DATABASE_NAME
```

`--confirm` must exactly match the configured database name (for `SP_APP_ENV=local` that is `sp_work`).

The script inventories **all** base tables in the PDO-selected database. It allows only:
- no base tables at all, or
- solely an empty `accounts` table.

Any other known application table, any unknown/legacy/custom table, any account row, or a failed table inventory is rejected before schema writes.

4. Log in as the new Master Admin.
5. Remove `SP_BOOTSTRAP_ADMIN_*` from `.env` / the process environment.

For `uat` / `production`, all of `SP_DB_HOST`, `SP_DB_NAME`, `SP_DB_USER`, and `SP_DB_PASS` must be non-empty.

Never put the bootstrap password in Git, SQL files, tickets, or command examples.

## Other CLI scripts

```bat
php scripts\fix-b64.php
```

Do **not** copy sensitive scripts back to the web root on live servers.
