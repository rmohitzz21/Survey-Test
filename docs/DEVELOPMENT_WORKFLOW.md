# Development workflow

How to make a safe change and open a GitHub pull request for this repository.

## Ground rules

- Do not commit `.env`, real credentials, `uploads/` binaries, or `storage/` sessions.
- Do not weaken CSRF or auth to “make tests pass.”
- Prefer small, reviewable PRs.
- Preserve any **held** local migrate/bootstrap files your team has not decided to publish — stage only the paths you intend to ship (`git add -- path`, never `git add .` when the working tree is mixed).

## 1. Start from an up-to-date `main`

```bat
git fetch origin
git switch main
git pull origin main
git switch -c feature/short-description
```

If you have unrelated local files, create the branch without discarding them. Confirm with `git status -sb` before and after.

## 2. Make the change

- Match existing patterns in `api/`, `includes/`, and `index.php`.
- For portal CSS, prefer `assets/css/portal.css` (or the other assets stylesheets) over new large inline `<style>` blocks.
- For shared fetch 401 handling, keep `assets/js/fetch-session-guard.js` as the single patch point.

## 3. Review your diff

```bat
git status -sb
git diff
git diff --check
```

Confirm secrets and held ops files are **not** in the change set.

## 4. Test locally

See [TESTING.md](TESTING.md). At minimum run unit tests relevant to your change; run Playwright smoke when UI/auth shell behavior might be affected.

## 5. Commit

Stage explicit paths only:

```bat
git add -- path\to\file1 path\to\file2
git commit -m "type: short summary"
```

Use conventional, specific messages (e.g. `fix: …`, `feat: …`, `test: …`, `docs: …`).

## 6. Push and open a PR

```bat
git push -u origin HEAD
```

Open a pull request into `main` on GitHub (web UI or `gh pr create`). Include:

- What changed and why  
- How you tested  
- Any risk notes (auth, migrations, utilities)

Do not force-push shared branches unless your team explicitly allows it.

## 7. After merge

```bat
git fetch origin
git switch main
git pull origin main
```

Delete the feature branch locally when finished.

## Repository remote (this project)

Default remote used by this codebase:

`https://github.com/rmohitzz21/Survey-Test.git`
