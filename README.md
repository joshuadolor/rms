# Restaurant Management System (RMS)

RESTful API backend for the Restaurant Management System. See [restaurant-management-system-prompt.md](./restaurant-management-system-prompt.md) for product vision and requirements.

## Quick start with Docker (recommended)

From the project root:

```bash
docker compose up --build
```

Then open:

- **http://localhost** — App via nginx (frontend + API on one origin; no CORS)
- **http://localhost:8080** — Frontend only (Vite; proxies `/api` to the API)
- **http://localhost:3000** — API only (e.g. `http://localhost:3000/api/health`)
- **http://localhost:8025** — Mailhog (dev mail; outbound mail from the API appears here)

Nginx uses **keepalive** to the API and frontend so repeated requests reuse connections and feel faster. If the first request is slow, later ones should be quicker.

### Update `/etc/hosts` (optional)

To use a custom local domain (e.g. `http://rms.local` instead of `http://localhost`), add it to your hosts file so it points to `127.0.0.1`.

**macOS / Linux:** edit `/etc/hosts` (needs `sudo`):

```bash
sudo nano /etc/hosts
```

Add a line:

```
127.0.0.1   rms.local
```

Save, then open **http://rms.local** in the browser. If you later use subdomains (e.g. `demo.rms.local` for restaurant menus), add those too:

```
127.0.0.1   rms.local
127.0.0.1   demo.rms.local
```

**Windows:** edit `C:\Windows\System32\drivers\etc\hosts` as Administrator and add the same lines.

After changing hosts, nginx still listens on port 80, so use **http://rms.local** (no port). If you use a custom domain, set `APP_URL` and `FRONTEND_URL` in the API’s `.env` (or docker-compose) to that URL (e.g. `http://rms.local`).

### Testing mail locally (Mailhog)

With Docker, the API sends mail to **Mailhog** (SMTP on port 1025). Open **http://localhost:8025** to see captured messages.

**1. Quick test (dev only)**  
Call the test endpoint to send a single email and confirm the mail driver:

```bash
curl http://localhost/api/test-mail
```

You should get `"message": "Test email sent. Check Mailhog at http://localhost:8025"` and see `mail_driver: smtp`, `smtp_host: mailhog`. Then open http://localhost:8025 and the test email should be there.

**2. Real flows**  
- **Register** — After registering a new user, a verification email is sent. Check Mailhog for it.  
- **Forgot password** — Use "Forgot password" with an existing email; the reset link appears in Mailhog.

**If no mail appears:**  
- Ensure containers are up: `docker compose ps` (api, mailhog, nginx, frontend).  
- Call `http://localhost/api/test-mail`. If the response shows `mail_driver: log` or an error, the API is not using SMTP. Rebuild and restart: `docker compose up --build -d`, then `docker compose exec api php artisan config:clear`.  
- Check API logs: `docker compose logs api`.

## Run locally without Docker

Requires PHP 8.2+ and Composer.

```bash
cd api
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve --port=3000
```

Then visit **http://localhost:3000** and **http://localhost:3000/api/health**.

## Why Laravel (not Lumen)?

This backend is **Laravel**, not Lumen, on purpose:

- **Laravel is the recommended choice** for API-only apps now; the Laravel team has narrowed the gap and suggests full Laravel for most APIs.
- **RMS will need** auth (Sanctum), queues (e.g. QR generation), scheduling (opening hours, item availability), and possibly broadcasting later—all built into Laravel.
- **Migrations and Eloquent** are the same in both; you get a smaller Lumen footprint but give up ecosystem and features we’ll likely need.
- **Lumen** still makes sense for very small, long-lived microservices; for a product like RMS that will grow (multi-restaurant, menus, combos, templates, paid tiers), Laravel is the better fit.

## Stack

- **Laravel** (API)
- **SQLite** (database; file: `api/database/database.sqlite`)
- **Docker** (optional, for consistent local runs)

## Project layout (DDD)

The API is structured with **Domain-Driven Design**:

```
api/app/
├── Domain/           # Bounded contexts and contracts
│   └── Auth/
│       └── Contracts/   # Repository interfaces
├── Application/      # Use cases (application services)
│   └── Auth/           # RegisterUser, LoginUser, LogoutUser, ForgotPassword, ResetPassword, SocialLogin
├── Infrastructure/   # Persistence and delivery
│   └── Persistence/Eloquent/Repositories/   # Repository implementations
├── Http/             # Controllers, Form Requests (thin; delegate to Application)
├── Models/           # Eloquent models (used by Infrastructure)
└── Notifications/
```

Restaurant/menu domains will be added when those features are built.

## Database (migrations)

Current migrations are **auth-only** (Laravel + Sanctum + social):

| Table | Purpose |
|-------|--------|
| `users` | Accounts (email/password + social) |
| `password_reset_tokens` | Password reset tokens |
| `personal_access_tokens` | Sanctum API tokens |
| `social_accounts` | OAuth provider link (provider, provider_id) |

- **Local (no Docker):** `cd api && php artisan migrate`. To reset: `cd api && php artisan migrate:fresh --force`.
- **Docker:** The app uses the DB in the volume at `/app/storage/db`, so running migrate inside the container affects that DB. To reset: `docker compose exec api php artisan migrate:fresh --force`. Running `php artisan migrate:fresh` on the **host** (`cd api`) resets the host file only, not the volume—run it in the container when using Docker.

## Auth API (for frontend)

All auth endpoints are under `/api`. Use **Bearer token** (Sanctum) for protected routes.

| Method | Endpoint | Body | Description |
|--------|----------|------|--------------|
| POST | `/api/register` | `name`, `email`, `password`, `password_confirmation` | Register; returns `user` + `token` |
| POST | `/api/login` | `email`, `password` | Login; returns `user` + `token` |
| POST | `/api/logout` | — | Revoke **current** token only (Bearer required) |
| POST | `/api/logout-all` | — | Revoke **all** of the user's tokens / logout everywhere (Bearer required) |
| POST | `/api/forgot-password` | `email` | Send reset link to email (link points to `FRONTEND_URL`) |
| POST | `/api/reset-password` | `token`, `email`, `password`, `password_confirmation` | Reset password (token from email link) |
| GET | `/api/user` | — | Current user (Bearer required) |

**Social login (token exchange):**  
Frontend completes OAuth with the provider (Google/Facebook/Instagram), then sends the provider’s **access_token** (or Google **id_token**) to the backend. Backend verifies the token and returns a Sanctum **token**.

| Method | Endpoint | Body | Description |
|--------|----------|------|--------------|
| POST | `/api/auth/google` | `access_token` or `id_token` | Returns `user` + `token` |
| POST | `/api/auth/facebook` | `access_token` | Returns `user` + `token` |
| POST | `/api/auth/instagram` | `access_token` | Returns `user` + `token` (Instagram Graph API) |

Set `FRONTEND_URL` and OAuth credentials in `.env` (see `api/.env.example`).

**Security:** Auth endpoints are rate-limited (login 5/min, register and forgot-password 3/min, social 10/min per IP); excess requests return `429 Too Many Requests`. Sanctum tokens expire after 7 days. Passwords require at least 8 characters with letters and numbers (register and reset-password).
