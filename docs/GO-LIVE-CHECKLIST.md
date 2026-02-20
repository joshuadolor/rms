# Go-live checklist

Use this list before taking RMS to production. Tick items as you complete them.

---

## Security

- [ ] **reCAPTCHA** on public forms (login, register, forgot password, **public restaurant feedback form**). Add and verify server-side before launch. See [reCAPTCHA](https://www.google.com/recaptcha/admin) (v2 or v3).
- [ ] **HTTPS** everywhere. No mixed content; API and frontend served over TLS.
- [ ] **Secrets** not in repo: `APP_KEY`, DB credentials, OAuth client secrets, reCAPTCHA secret key, and any API keys in env only (and `.env` in `.gitignore`).
- [ ] **APP_DEBUG=false** and **APP_ENV=production** in production.
- [ ] **CORS** restricted: set `CORS_ALLOWED_ORIGINS` in API to your production frontend origin(s) only.
- [ ] **Session**: consider `SESSION_SECURE_COOKIE=true` and `SESSION_SAME_SITE=lax` (or `strict`) in production.

---

## Configuration

- [ ] **API `.env`**
  - `APP_URL` = production API URL (e.g. `https://api.example.com`).
  - `FRONTEND_URL` = production frontend URL (for password reset emails and CORS).
  - `RESTAURANT_DOMAIN` = production restaurant domain (e.g. `menus.example.com`). See [subdomain setup](subdomain-setup.md).
  - Production DB (e.g. MySQL/PostgreSQL); run migrations.
  - Mail driver configured (e.g. SMTP) so password reset and verification emails send.
- [ ] **Frontend `.env`**
  - `VITE_API_URL` = production API base URL (e.g. `https://api.example.com/api`), or rely on same-origin if front and API share a domain.
  - `VITE_APP_PUBLIC_DOMAIN` = same as API `RESTAURANT_DOMAIN` (e.g. `menus.example.com`).
  - OAuth (Google/Facebook/Instagram): production client IDs and redirect URIs configured in provider consoles and in env.

---

## DNS & hosting

- [ ] **Wildcard DNS** for restaurant subdomains: e.g. `*.menus.example.com` → your server. See [subdomain-setup.md](subdomain-setup.md).
- [ ] **Nginx** (or reverse proxy): `server_name *.menus.example.com menus.example.com;` and correct `location` blocks for `/`, `/api`, `/sanctum`.
- [ ] **SSL** for main domain and wildcard (`*.menus.example.com`), e.g. Let’s Encrypt.

---

## Data & backups

- [ ] **Database** backups scheduled (frequency and retention that match your needs).
- [ ] **File storage**: logo/banner uploads on a persistent disk (or object storage); path and permissions correct in production.

---

## Testing & health

- [ ] **Smoke test**: register → verify email → login → create restaurant → add menu item → open public page (`[slug].domain.com` or `/r/[slug]`).
- [ ] **Password reset** flow: request reset email and use link; confirm it works with production mail.
- [ ] **Critical paths** (e.g. login, register, restaurant CRUD, public menu) tested in production-like environment.
- [ ] **Error reporting**: consider production error tracking (e.g. Sentry, Flare) for API and/or frontend.

---

## Performance & ops

- [ ] **Cache**: production cache driver (e.g. Redis) if you use caching; `php artisan config:cache` and `route:cache` after deploy.
- [ ] **Queue**: if you use queues, workers running in production (e.g. supervisor or platform queue worker).
- [ ] **Logs**: `LOG_LEVEL=production` or similar; log aggregation or rotation so disks don’t fill.

---

## Optional / later

- [ ] Rate limiting on login/register (Laravel throttle or gateway).
- [ ] Cookie / privacy policy and consent if required (e.g. EU).
- [ ] Monitoring and uptime checks (e.g. health endpoint + external ping).

---

*Update this checklist as your launch process evolves.*
