# Testing

All commands assume the **project root** unless noted. Prefer XAMPP’s PHP binary on Windows when `php` is not on PATH, e.g. `C:\xampp\php\php.exe`.

## Safety

- Run tests against **local** XAMPP + `sp_work` only.
- Never point Playwright `SP_BASE` or integration tests at production.
- Do not commit `.env`, credentials, Playwright reports, or `node_modules`.

## PHP unit / smoke (no browser)

Examples that do not require a full HTTP stack:

```bat
php tests\due-ts-test.php
php tests\complete-guard-test.php
php tests\leave-notice-test.php
php tests\slots-test.php
php tests\session-cookie-test.php
php tests\manage-user-test.php
php tests\resources-test.php
php tests\demo-data-test.php
```

`tests\notes-test.php` uses local MySQL `sp_work` and cleans up its own marker rows.

## Node script tests (no Playwright)

```bat
node tests\calendar-test.js
node tests\reports-view-test.js
node tests\teams-test.js
node tests\warnings-test.js
node tests\month-scope-test.js
node tests\search-test.js
```

## Feature integration (Apache + MySQL)

```bat
php tests\feature-integration-test.php
```

- Hits `http://localhost/SurveyPacific/Survey_work_journey/Survey-test` by default (`SP_BASE` env overrides).
- Creates/reads QA data; leave section cleans `QA integration leave%` rows on local `sp_work` only.
- Requires Apache and a populated local DB with seed users your environment already uses.

**Verified locally (post leave-fixture fix):** integration run reported **83 passed, 0 failed** (consecutive re-runs also passed in that verification pass).

## Browser smoke (Playwright)

Isolated package under `tests/browser/` (own `package.json` — not a root React app).

```bat
cd tests\browser
npm install
npx playwright install chromium
npx playwright test
```

### Configuration

Copy `tests/browser/.env.example` → `tests/browser/.env` (gitignored):

```env
SP_BASE=http://localhost/SurveyPacific/Survey_work_journey/Survey-test/

# Optional — when unset, authenticated tests are skipped (not marked passed)
SP_ADMIN_EMAIL=
SP_ADMIN_PASSWORD=
SP_MEMBER_EMAIL=
SP_MEMBER_PASSWORD=
```

`SP_BASE` must end with `/` so relative paths resolve under this project folder.

### What the smoke suite covers

1. Login page loads; form fields usable  
2. Unauthenticated `index.php` redirects to login  
3. `assets/css/portal.css` and `assets/js/fetch-session-guard.js` return OK  
4. Optional authenticated admin/member portal load (env credentials)  
5. No unexpected `pageerror` events during those loads  

It does **not** submit leave, approve records, or perform other mutating business workflows.

### Verified locally

| Mode | Result |
|------|--------|
| Without credentials | **3 passed**, 0 failed, **2 skipped** |
| With local env credentials | **5 passed**, 0 failed, 0 skipped |

## Suggested minimum before a PR

```bat
php tests\leave-notice-test.php
php tests\session-cookie-test.php
php tests\due-ts-test.php
cd tests\browser && npx playwright test
```

Add `feature-integration-test.php` when your change touches cross-cutting portal/API behavior and your local stack is up.
