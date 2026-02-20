# E2E tests (Playwright)

End-to-end tests for the RMS frontend. **Authentication and profile tests use mocked API responses**, so you can run them without the backend.

## Auth flow and email delivery

Most **auth** tests mock the API, so they run without the backend. The same **auth.spec.cjs** file ends with **Email delivery (real API + Mailhog)** tests: they use the real API and Mailhog (no mocks). Those two tests are **skipped** if the API or Mailhog are unreachable, so `npm run test:e2e` always runs the full auth suite; the email-delivery tests only run when you have the API and Mailhog up (e.g. API on port 3000, Mailhog on 8025).

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

- **Base URL**: `http://localhost:8082` by default. Override with `PLAYWRIGHT_BASE_URL` (e.g. `http://rms.local:8082`).
- **API**: Auth and profile tests mock the API. The **Email delivery** tests in auth.spec.cjs use the real API and Mailhog; they are skipped if API or Mailhog are unreachable. To run them: have the API on port 3000 (`MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`) and Mailhog on port 8025. Set **`MAILHOG_URL`** if Mailhog is elsewhere (default `http://localhost:8025`). **Cleanup:** after the Email delivery tests finish, `php artisan e2e:cleanup-users` is run automatically (from the `api` directory, or via **`E2E_CLEANUP_CMD`** for Docker, e.g. `E2E_CLEANUP_CMD="docker compose exec api php artisan e2e:cleanup-users"`). See `api/docs/e2e-cleanup.md`.

## Scope

- **auth.spec.cjs**: Landing, login (email/password), register (email), **forgot password** (success, empty/invalid email validation, back to sign in), reset password page, verify-email page, route guards, logout, and **Email delivery (real API + Mailhog)**: register sends verification email, resend link sends verification email, forgot password sends reset email (skipped if API/Mailhog unreachable). **SSO is not tested**.
- **profile.spec.cjs**: Profile & Settings — unauthenticated redirect, page content, navigation, update profile, change password. All API calls mocked.
- **restaurant.spec.cjs**: Restaurant module — unauthenticated redirect, list (empty state, header), create form and create flow, manage page (Profile, Menu, Availability, Settings tabs), add menu item (full page and FAB modal), not found. All API calls mocked. **Translate by default / Translate from default is not tested** (not yet implemented).
- **superadmin.spec.cjs**: Superadmin module — access control (regular user redirected from /app/superadmin/users and /app/superadmin/restaurants; superadmin can access), superadmin login and nav (sidenav shows Dashboard, Users, Restaurants, Profile & Settings; does not show Menu items, Feedbacks), dashboard stats, users list and paid/active toggle (mocked), restaurants list (mocked). All API calls mocked.

## Shared helpers

- **e2e/helpers/e2e-user.cjs** — `createE2eUser(request, options)` registers a user via the real API and returns `{ email, password, name }`. Use in any test that needs a real account (e.g. login, profile). Options: `{ emailPrefix?, name?, password? }`. Users match `e2e-%@example.com` and are removed by `e2e:cleanup-users` after the Email delivery block (or run the command manually).
