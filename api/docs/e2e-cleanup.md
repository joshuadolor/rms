# E2E user cleanup

E2E tests (Playwright, under `frontend/e2e/`) create users with emails like `e2e-resend-{timestamp}@example.com`. Over time the dev database can fill up with these accounts. This document describes how to remove them safely.

## Artisan command (required)

**Command:** `e2e:cleanup-users`

**How to run:**

```bash
php artisan e2e:cleanup-users
```

**What it does:** Deletes users whose email matches the configured E2E patterns (see `config/e2e.php`). Default pattern is `e2e-%@example.com`, which matches `e2e-resend-*@example.com` and any other `e2e-*@example.com` addresses. The command outputs how many users were deleted.

**Safety:** The command **only runs when `APP_ENV=local` or `APP_ENV=testing`**. If you run it in production (or any other environment), it exits with an error and **does not delete any users**.

E2E tests can run the command directly (e.g. in an `afterAll` hook) via the API container: `docker compose exec api php artisan e2e:cleanup-users`, or by shell if the API runs locally.

---

## Adding more E2E patterns

Edit `config/e2e.php` and add SQL `LIKE` patterns to the `cleanup_email_patterns` array (e.g. `e2e-profile-%@example.com`). No code changes are required; the command uses this config.
