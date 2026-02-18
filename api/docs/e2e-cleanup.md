# E2E cleanup

E2E tests (Playwright, under `frontend/e2e/`) create **users** with emails like `e2e-resend-{timestamp}@example.com`. Those users may own restaurants and other data. Over time the dev database can fill up. This document is the single place for how to clean that data safely.

**What we clean up:** E2E test users matching the configured email pattern (default `e2e-%@example.com`). Deleting those users also removes their related data (restaurants, tokens, etc.) via database cascade. No separate cleanup for restaurants is required.

**Command:** `e2e:cleanup-users`  
**Where it lives:** `api/app/Console/Commands/E2eCleanupUsersCommand.php` (uses `App\Services\E2eCleanupService`).

---

## How to run

**From the API directory (e.g. after e2e or locally):**

```bash
cd api && php artisan e2e:cleanup-users
```

**One-liner from project root:**

```bash
php artisan e2e:cleanup-users
```

(Run from the `api` directory, or from project root with `api` as cwd.)

**Docker one-liner (when e2e runs with API in Docker):**

```bash
docker compose exec api php artisan e2e:cleanup-users
```

---

## Wiring into E2E

After the **Email delivery** block in `frontend/e2e/auth.spec.cjs`, the suite runs the cleanup in an `afterAll` hook. You can control how it runs with:

- **`E2E_CLEANUP_CMD`** — If set, the hook runs this command (e.g. when the API runs in Docker). Example:

  ```bash
  E2E_CLEANUP_CMD="docker compose exec api php artisan e2e:cleanup-users" npm run test:e2e
  ```

- If **`E2E_CLEANUP_CMD`** is not set, the hook looks for the `api` directory (relative to `frontend/` or cwd) and runs `php artisan e2e:cleanup-users` from there. If `api` is not found, cleanup is skipped and a warning is logged.

---

## Safety

- **Environment guard:** The command runs **only when `APP_ENV=local` or `APP_ENV=testing`**. In any other environment (e.g. `production`, `staging`) it exits with an error and **does not delete anything**.
- **Idempotent:** Safe to run multiple times; running after e2e or locally has no side effects on production.
- **Scoped:** Only users whose email matches the configured E2E patterns are deleted (see `config/e2e.php` → `cleanup_email_patterns`). Default: `e2e-%@example.com`.

---

## Adding more E2E patterns

Edit `config/e2e.php` and add SQL `LIKE` patterns to the `cleanup_email_patterns` array (e.g. `e2e-profile-%@example.com`). No code changes are required; the command uses this config.

---

## Pattern for future cleanups

Follow the same pattern as this command (e2e:cleanup-users): Artisan command in `api/`, env guard so it only runs in `local`/`testing`, config-driven patterns, and document the one-liner and Docker one-liner in this doc (or a linked doc). Wire into e2e via `E2E_CLEANUP_CMD` or a dedicated env var if you add a second cleanup (e.g. a separate command for other e2e data).
