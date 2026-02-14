# RMS Backend API Reference

Single source of truth for the Restaurant Management System API. **Frontend agents: read this document when implementing or changing any API calls.** Backend agents: add every new endpoint here.

- **Base URL**: `/api`. In dev, the frontend can use a **reverse proxy** (Vite proxies `/api` and `/sanctum` to the API) so the app uses same-origin `/api` and avoids CORS; then the browser hits e.g. `http://localhost:8080/api` and Vite forwards to the API. Without proxy, use the full API URL (e.g. `http://localhost:3000/api`).
- **JSON**: All request bodies and responses are JSON. Send `Content-Type: application/json` and `Accept: application/json`.
- **Auth**: Protected routes use **Bearer token** (Laravel Sanctum). Send `Authorization: Bearer <token>`.
- **Errors**: Validation errors return **422** with `{ "message": "...", "errors": { "field": ["..."] } }`. Auth errors return **401** or **403** with `{ "message": "..." }`.

---

## User payload (common)

Used wherever the API returns a user object:

```ts
{
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;  // ISO 8601 datetime
}
```

---

## Endpoints

### Health

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/health` | No | Service health check. |

**Response (200):**
```json
{
  "status": "ok",
  "service": "Restaurant Management System API",
  "timestamp": "2025-02-13T12:00:00.000000Z"
}
```

---

### Auth (guest)

#### Register

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| POST | `/api/register` | No | 3/min |

**Body:**
```json
{
  "name": "string (required, max 255)",
  "email": "string (required, email, unique)",
  "password": "string (required, min 8, letters + numbers)",
  "password_confirmation": "string (required, must match password)"
}
```

**Response (201):** No token. User must verify email before logging in.
```json
{
  "message": "Registered. Please verify your email using the link we sent you.",
  "user": { "id", "name", "email", "email_verified_at" }
}
```

---

#### Login

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| POST | `/api/login` | No | 5/min |

**Body:**
```json
{
  "email": "string (required)",
  "password": "string (required)"
}
```

**Response (200):** Only if email is verified.
```json
{
  "message": "Logged in successfully.",
  "user": { "id", "name", "email", "email_verified_at" },
  "token": "string",
  "token_type": "Bearer"
}
```

**Errors:** Use **403** for unverified email; use **422** only for wrong credentials or validation.
- **422** – Invalid credentials or validation: `errors.email` = "The provided credentials are incorrect." (and/or other validation `errors`).
- **403** – Email not verified: `{ "message": "Your email address is not verified." }`. Never 404 or 422 for this case.

---

#### Forgot password

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| POST | `/api/forgot-password` | No | 3/min |

**Body:**
```json
{
  "email": "string (required, email)"
}
```

**Response (200):** Same message whether or not the email exists (no enumeration).
```json
{
  "message": "If that email exists in our system, we have sent a password reset link."
}
```

---

#### Reset password

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/reset-password` | No |

**Body:**
```json
{
  "token": "string (required)",
  "email": "string (required, email)",
  "password": "string (required, min 8, letters + numbers)",
  "password_confirmation": "string (required)"
}
```

**Response (200):**
```json
{
  "message": "Your password has been reset."
}
```

**Errors:** 422 if token invalid/expired or validation fails.

---

### Email verification

#### Verify email (signed link)

The verification email contains a link to the **frontend** at `{FRONTEND_URL}/email/verify?id=...&hash=...&expires=...&signature=...`. The frontend page calls the API below to perform verification, then shows success or error.

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/email/verify/{id}/{hash}?expires=...&signature=...` | No (signed) |

Query params `expires` and `signature` are added by the backend when building the link.

**Response (200):**
```json
{
  "message": "Email verified successfully. You can now log in.",
  "user": { "id", "name", "email", "email_verified_at" }
}
```

If already verified:
```json
{
  "message": "Email already verified.",
  "user": { "id", "name", "email", "email_verified_at" }
}
```

**Errors:** 422 for invalid/expired link (`errors.email`).

---

#### Resend verification email

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| POST | `/api/email/resend` | Optional | 3/min (same as forgot-password) |

**Guest:** send email in body.
**Body (when not authenticated):**
```json
{
  "email": "string (required, email)"
}
```

**Response (200):** Generic message (no enumeration).
```json
{
  "message": "If that email exists and is unverified, we have sent a new verification link."
}
```

If authenticated and already verified:
```json
{
  "message": "Email already verified."
}
```

If authenticated and unverified:
```json
{
  "message": "Verification link sent. Please check your email."
}
```

---

### Social login

Frontend completes OAuth with the provider, then sends the provider’s access token (or id_token for Google) to the API. Rate limit: 10/min per endpoint.

#### Google

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/auth/google` | No |

**Body:** One of:
```json
{ "access_token": "string" }
```
or
```json
{ "id_token": "string" }
```

**Response (200):**
```json
{
  "message": "Logged in successfully.",
  "user": { "id", "name", "email", "email_verified_at" },
  "token": "string",
  "token_type": "Bearer"
}
```

**Errors:** 401 – `{ "message": "Invalid or expired Google token." }`

---

#### Facebook

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/auth/facebook` | No |

**Body:**
```json
{
  "access_token": "string (required)"
}
```

**Response (200):** Same shape as Google. **Errors:** 401 – "Invalid or expired Facebook token."

---

#### Instagram

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/auth/instagram` | No |

**Body:**
```json
{
  "access_token": "string (required)"
}
```

**Response (200):** Same shape as Google. **Errors:** 401 – "Invalid or expired Instagram token."

---

### Auth (protected)

All require `Authorization: Bearer <token>` and **verified email**.

**Unverified email:** The server returns **403 Forbidden** (never 404 or 422). The route and resource exist; access is refused due to account state. Response body is always JSON:
```json
{ "message": "Your email address is not verified." }
```

#### Current user

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/user` | Bearer + verified |

**Response (200):**
```json
{
  "user": { "id", "name", "email", "email_verified_at" }
}
```

**Errors:** 401 if invalid/missing token; **403** if email not verified (body: `{ "message": "Your email address is not verified." }`).

---

#### Logout

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/logout` | Bearer + verified |

**Response (200):**
```json
{
  "message": "Logged out successfully."
}
```

---

#### Logout all devices

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/logout-all` | Bearer + verified |

**Response (200):**
```json
{
  "message": "Logged out from all devices successfully."
}
```

---

## Changelog

- **2025-02-13**: Verification email link now points to frontend `/email/verify` (frontend then calls API). Reset link already points to frontend `/reset-password`.
- **2025-02-13**: Login when email not verified: return **403** with `{ "message": "Your email address is not verified." }`; keep **422** for wrong credentials/validation only. API reference Login section and errors clarified.
- **2025-02-13**: Initial document – health, register (no token, verify email), login (block unverified), forgot/reset password, email verify/resend, social login (Google/Facebook/Instagram), user, logout, logout-all.
