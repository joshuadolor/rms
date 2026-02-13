# Restaurant Management System (RMS)

RESTful API backend for the Restaurant Management System. See [restaurant-management-system-prompt.md](./restaurant-management-system-prompt.md) for product vision and requirements.

## Quick start with Docker (recommended)

From the project root:

```bash
docker compose up --build
```

Then open:

- **http://localhost:3000** — API welcome (JSON)
- **http://localhost:3000/api/health** — Health check (JSON)

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

Run: `cd api && php artisan migrate`. If you previously ran removed restaurant migrations, use `php artisan migrate:fresh` for a clean auth-only DB.

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
