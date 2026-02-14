# E2E tests (Playwright)

End-to-end tests for the RMS frontend. **Authentication tests use mocked API responses**, so you can run them without the backend.

## Run tests

From the `frontend` directory:

```bash
npm run test:e2e
```

This starts the Vite dev server (if not already running) and runs tests in Chromium. To open the Playwright UI:

```bash
npm run test:e2e:ui
```

## Environment

- **Base URL**: `http://localhost:8082` by default (e2e dev server runs on 8082 to avoid port conflicts). Override with `PLAYWRIGHT_BASE_URL`.
- **API**: Auth flows are mocked in the tests (login, register, forgot-password, logout). You do **not** need the Laravel API running for the current auth suite.
- To run e2e against a **real API** later, you would start the API (e.g. `php artisan serve` in `api/`), set `VITE_PROXY_TARGET` so the frontend proxies `/api` to it, and remove or adjust the route mocks in `e2e/auth.spec.js`.

## Scope

- **auth.spec.cjs**: Landing, login (email/password), register (email), forgot password, reset password page, verify-email page, route guards, logout. **SSO (Google/Facebook/Instagram) is not tested** and is left for later.
- **profile.spec.cjs**: Profile & Settings — unauthenticated redirect, page content (profile form, change password form), navigation from sidebar, update profile (success and validation error), change password (wrong current password error, success and form clear). All API calls (GET/PATCH `/api/user`, POST `/api/profile/password`) are mocked.
