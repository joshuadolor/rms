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
  uuid: string;                      // public identifier (UUID); internal id is never exposed
  name: string;
  email: string;
  email_verified_at: string | null;  // ISO 8601 datetime
  pending_email?: string | null;     // when an email change is pending (e.g. GET /user, PATCH /user)
  is_paid?: boolean;                  // reserved for future paid features (e.g. multiple restaurants)
  is_superadmin?: boolean;           // true only for the superadmin user
  is_active?: boolean;               // false when account has been deactivated by superadmin
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
  "user": { "uuid", "name", "email", "email_verified_at" }
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

**Response (200):** Only if email is verified and account is active.
```json
{
  "message": "Logged in successfully.",
  "user": { "uuid", "name", "email", "email_verified_at", "pending_email", "is_paid", "is_superadmin", "is_active" },
  "token": "string",
  "token_type": "Bearer"
}
```

**Refresh token cookie:** On successful login, the API also sets an **HttpOnly** refresh token cookie named **`rms_refresh`** (SameSite=Lax, Path=/, Secure when not in local environment, or whenever SameSite is `none`). The refresh token is **not** returned in JSON. The cookie is used by `POST /api/auth/refresh` to obtain a new access token on page reload and is **rotated on every refresh**.

**Errors:** Use **403** for unverified email or deactivated account; use **422** only for wrong credentials or validation.
- **422** – Invalid credentials or validation: `errors.email` = "The provided credentials are incorrect." (and/or other validation `errors`).
- **403** – Email not verified: `{ "message": "Your email address is not verified." }`. Never 404 or 422 for this case.
- **403** – Account deactivated: `{ "message": "Your account has been deactivated." }`.

---

#### Refresh access token

Use the refresh token stored in the **HttpOnly** cookie to get a new access token. **No Bearer token is required.**

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| POST | `/api/auth/refresh` | No (refresh cookie) | 30/min |

**Body:** none.

**Response (200):**
```json
{
  "message": "Token refreshed successfully.",
  "user": { "uuid", "name", "email", "email_verified_at", "pending_email", "is_paid", "is_superadmin", "is_active" },
  "token": "string",
  "token_type": "Bearer"
}
```

**Behavior:** On success, the API **rotates** the refresh token by setting a **new** `rms_refresh` cookie and revoking the previous refresh token server-side. On **401** or **403**, the API also **clears** the refresh cookie to prevent client refresh loops and to avoid stale-cookie downgrade behavior.

**Errors:**
- **401** – Missing/invalid/expired/revoked refresh token cookie (also clears refresh cookie): `{ "message": "Invalid or expired refresh token." }`
- **403** – Email not verified (also clears refresh cookie): `{ "message": "Your email address is not verified." }`
- **403** – Account deactivated (also clears refresh cookie): `{ "message": "Your account has been deactivated." }`

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

The verification email contains a link to the **frontend** at `{FRONTEND_URL}/email/verify?uuid=...&hash=...&expires=...&signature=...`. The frontend page calls the API below to perform verification, then shows success or error.

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/email/verify/{uuid}/{hash}?expires=...&signature=...` | No (signed) |

Query params `expires` and `signature` are added by the backend when building the link.

**Response (200):**
```json
{
  "message": "Email verified successfully. You can now log in.",
  "user": { "uuid", "name", "email", "email_verified_at" }
}
```

If already verified:
```json
{
  "message": "Email already verified.",
  "user": { "uuid", "name", "email", "email_verified_at" }
}
```

**Errors:** 422 for invalid/expired link (`errors.email`).

---

#### Verify new email (after profile email change)

When a user changes their email in profile, a verification link is sent to the **new** address. That link points to the frontend at `{FRONTEND_URL}/email/verify-new?uuid=...&hash=...&expires=...&signature=...`. The frontend calls the API below to complete the change.

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/email/verify-new/{uuid}/{hash}?expires=...&signature=...` | No (signed) |

**Response (200):**
```json
{
  "message": "Your email has been updated and verified.",
  "user": { "uuid", "name", "email", "email_verified_at" }
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
  "user": { "uuid", "name", "email", "email_verified_at" },
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
  "user": { "uuid", "name", "email", "email_verified_at", "pending_email", "is_paid", "is_superadmin", "is_active" }
}
```

**Errors:** 401 if invalid/missing token; **403** if email not verified (body: `{ "message": "Your email address is not verified." }`).

---

#### Update profile

| Method | Path | Auth |
|--------|------|------|
| PATCH | `/api/user` | Bearer + verified |

**Body:** Send only the fields you want to change.
```json
{
  "name": "string (optional, max 255)",
  "email": "string (optional, email, unique; triggers verification flow)"
}
```

- **Name:** Updated immediately.
- **Email:** If provided and different from current email, the new address is stored as **pending**. A verification link is sent to the new email; the stored `email` is only updated after the user clicks that link. Until then, login and account remain on the current email.

**Response (200) when only name updated or no email change:**
```json
{
  "message": "Profile updated.",
  "user": { "uuid", "name", "email", "email_verified_at", "pending_email" }
}
```

**Response (200) when email change requested (verification sent):**
```json
{
  "message": "A verification link has been sent to your new email address. Please confirm to complete the change.",
  "user": { "uuid", "name", "email", "email_verified_at", "pending_email" }
}
```

**Errors:** 422 validation (e.g. email already in use). **401/403** as for other protected routes.

---

#### Change password

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/profile/password` | Bearer + verified |

**Body:**
```json
{
  "current_password": "string (required)",
  "password": "string (required, min 8, letters + numbers)",
  "password_confirmation": "string (required)"
}
```

**Response (200):**
```json
{
  "message": "Password updated successfully."
}
```

**Errors:** 422 if current password wrong or validation fails (`errors.current_password`, `errors.password`).

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

**Behavior:** Revokes the current access token (Sanctum) and, if a refresh cookie is present, revokes that refresh token and clears the `rms_refresh` cookie.

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

**Behavior:** Revokes **all** access tokens and **all** refresh tokens for the user and clears the `rms_refresh` cookie.

---

## Superadmin

Endpoints for the **superadmin** user only. All require **Bearer + verified + superadmin**. If the authenticated user is not a superadmin, the API returns **403** with `{ "message": "Forbidden." }`. No internal `id` in any response.

Superadmin is identified by a user record with `is_superadmin = true`, seeded from `SUPERADMIN_EMAIL` and `SUPERADMIN_PASSWORD` in `.env` (see SuperadminSeeder). They log in via normal POST `/api/login`.

### Dashboard stats

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/superadmin/stats` | Bearer + verified + superadmin |

**Response (200):**
```json
{
  "data": {
    "restaurants_count": 0,
    "users_count": 1,
    "paid_users_count": 0
  }
}
```

**403** – Not a superadmin.

---

### List all users

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/superadmin/users` | Bearer + verified + superadmin |

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "name": "string",
      "email": "string",
      "email_verified_at": "string | null",
      "pending_email": "string | null",
      "is_paid": false,
      "is_active": true,
      "is_superadmin": false
    }
  ]
}
```

User payload for admin list: uuid, name, email, email_verified_at, pending_email, is_paid, is_active, is_superadmin. No internal `id`.

**403** – Not a superadmin.

---

### Update user (deactivate or make paid)

| Method | Path | Auth |
|--------|------|------|
| PATCH | `/api/superadmin/users/{user}` | Bearer + verified + superadmin |

**Path:** `{user}` is the user's **uuid**.

**Body (JSON):** Send only the fields to change.
```json
{
  "is_paid": "boolean (optional)",
  "is_active": "boolean (optional)"
}
```

- **is_paid:** Set to true/false for paid tier (e.g. multiple restaurants).
- **is_active:** Set to false to deactivate the user (they cannot log in); true to reactivate. The superadmin **cannot change their own** `is_active` (request body with `is_active` for self returns 422).

**Response (200):**
```json
{
  "message": "User updated.",
  "data": { "uuid", "name", "email", "email_verified_at", "pending_email", "is_paid", "is_active", "is_superadmin" }
}
```

**403** – Not a superadmin. **404** – User not found for uuid. **422** – Validation (e.g. is_active sent for self).

---

### List all restaurants

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/superadmin/restaurants` | Bearer + verified + superadmin |

Read-only list of all restaurants (same payload shape as owner GET `/api/restaurants` list item).

**Response (200):**
```json
{
  "data": [ { restaurant payload } ]
}
```

Restaurant payload: uuid, name, tagline, primary_color, slug, year_established, public_url, address, latitude, longitude, phone, email, website, social_links, default_locale, currency, operating_hours, languages, logo_url, banner_url, created_at, updated_at. No internal `id`.

**403** – Not a superadmin.

---

## Owner feedback (feature requests)

Restaurant owners can submit feedback or feature requests (e.g. "I need X feature") for the superadmin to view and implement. All owner endpoints require **Bearer + verified**. No internal `id` in any response; use `uuid` only.

### Owner: Submit feedback

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/owner-feedback` | Bearer + verified |

**Body:**
```json
{
  "message": "string (required, max 65535)",
  "title": "string (optional, max 255)",
  "restaurant": "string (optional, uuid of a restaurant the user owns — for context)"
}
```

**Response (201):**
```json
{
  "message": "Feedback submitted.",
  "data": {
    "uuid": "string",
    "title": "string | null",
    "message": "string",
    "status": "pending",
    "created_at": "string (ISO 8601)",
    "submitter": { "uuid": "string", "name": "string" },
    "restaurant": { "uuid": "string", "name": "string" } | null
  }
}
```

**422** – Validation (e.g. missing message, invalid uuid). **403** – Restaurant uuid sent but not owned by the current user.

---

### Owner: List my feedbacks

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/owner-feedback` | Bearer + verified |

List the current user's own submissions (newest first).

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "title": "string | null",
      "message": "string",
      "status": "string (e.g. pending | reviewed)",
      "created_at": "string (ISO 8601)",
      "restaurant": { "uuid": "string", "name": "string" } | null
    }
  ]
}
```

---

### Superadmin: List all owner feedbacks

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/superadmin/owner-feedbacks` | Bearer + verified + superadmin |

List **all** owner feedbacks (from all users), newest first.

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "title": "string | null",
      "message": "string",
      "status": "string",
      "created_at": "string (ISO 8601)",
      "submitter": { "uuid": "string", "name": "string", "email": "string" },
      "restaurant": { "uuid": "string", "name": "string" } | null
    }
  ]
}
```

**403** – Not a superadmin.

---

### Superadmin: Update feedback status

| Method | Path | Auth |
|--------|------|------|
| PATCH | `/api/superadmin/owner-feedbacks/{feedback}` | Bearer + verified + superadmin |

**Path:** `{feedback}` is the feedback **uuid**.

**Body (optional):**
```json
{
  "status": "string (optional: pending | reviewed)"
}
```

Allows superadmin to mark feedback as reviewed when implemented.

**Response (200):**
```json
{
  "message": "Feedback updated.",
  "data": {
    "uuid": "string",
    "title": "string | null",
    "message": "string",
    "status": "string",
    "created_at": "string (ISO 8601)",
    "submitter": { "uuid": "string", "name": "string", "email": "string" },
    "restaurant": { "uuid": "string", "name": "string" } | null
  }
}
```

**403** – Not a superadmin. **404** – Feedback not found for uuid. **422** – Validation (e.g. invalid status).

---

## Restaurants

Restaurant management: CRUD and media (logo, page banner). All write/read-own endpoints require **Bearer + verified**. Media files are served via public URLs so they can be used in `<img src="...">`.

**Free tier:** Each user may have **one restaurant** only. Creating a second restaurant returns **403** with a message that the free tier allows one restaurant and to upgrade to add more.

### Restaurant payload (common)

Returned by list, show, create, update, and after logo/banner upload:

```ts
{
  uuid: string;
  name: string;
  tagline: string | null;
  primary_color: string | null;  // hex, e.g. #ff5500; for public site theming
  slug: string;
  template: string;         // e.g. "template-1" or "template-2"; valid values in config('templates.ids')
  year_established: number | null;  // e.g. 1995 for "Est. 1995"; 1800–(current year + 1)
  public_url: string;       // e.g. https://slug.yourapp.com
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  social_links: { facebook?: string; instagram?: string; twitter?: string; linkedin?: string };
  default_locale: string;
  currency: string;         // ISO 4217 code, e.g. USD, EUR; used for price display in restaurant context
  operating_hours: OperatingHours | null;  // optional; when null, not set or cleared
  languages: string[];      // installed locales
  logo_url: string | null;   // e.g. https://api.example.com/api/restaurants/{uuid}/logo
  banner_url: string | null; // e.g. https://api.example.com/api/restaurants/{uuid}/banner
  created_at: string;       // ISO 8601
  updated_at: string;       // ISO 8601
}
```

**Operating hours shape** (same structure is reused for menu item availability):

```ts
type OperatingHours = Record<Day, DayHours>;
type Day = 'sunday' | 'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday';
type DayHours = { open: boolean; slots: Array<{ from: string; to: string }> };
// Times are 24h strings, e.g. "09:00", "21:00" (HH:MM or HH:MM:SS). Per day, timeslots must not overlap.
```

**Note:** Internal numeric `id` is never exposed; only `uuid` is used in URLs and responses.

---

### Owner dashboard stats

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/dashboard/stats` | Bearer + verified |

Returns counts for the authenticated owner: restaurants, catalog menu items, and feedbacks (total, approved, rejected).

**Response (200):**
```json
{
  "data": {
    "restaurants_count": 2,
    "menu_items_count": 12,
    "feedbacks_total": 5,
    "feedbacks_approved": 3,
    "feedbacks_rejected": 2
  }
}
```

- **restaurants_count:** Number of restaurants owned by the user.
- **menu_items_count:** Number of standalone (catalog) menu items owned by the user (from the Menu items page).
- **feedbacks_total:** Total feedbacks across all of the user's restaurants.
- **feedbacks_approved:** Feedbacks with `is_approved: true`.
- **feedbacks_rejected:** Feedbacks with `is_approved: false`.

---

### List restaurants

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants` | Bearer + verified |

**Query:** `per_page` (optional, 1–50, default 15).

**Response (200):**
```json
{
  "data": [ { restaurant payload } ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

Returns only restaurants owned by the authenticated user.

---

### Show restaurant

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{uuid}` | Bearer + verified |

**Response (200):** `{ "data": { restaurant payload } }`  
**404:** Restaurant not found or not owned by user.

---

### Create restaurant

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants` | Bearer + verified |

**Body (JSON):**
```json
{
  "name": "string (required, max 255)",
  "tagline": "string (optional, max 255)",
  "primary_color": "string (optional; hex e.g. #f00, #ff5500, #ff5500cc)",
  "slug": "string (optional; default: slug from name, unique)",
  "template": "string (optional; one of config('templates.ids'), e.g. template-1, template-2; default: template-1)",
  "address": "string (optional, max 1000)",
  "latitude": "number (optional, -90 to 90)",
  "longitude": "number (optional, -180 to 180)",
  "phone": "string (optional, max 50)",
  "email": "string (optional, email)",
  "website": "string (optional, URL, max 500)",
  "social_links": {
    "facebook": "string (optional, URL)",
    "instagram": "string (optional, URL)",
    "twitter": "string (optional, URL)",
    "linkedin": "string (optional, URL)"
  },
  "default_locale": "string (optional, e.g. en, nl, ru; must be in supported locales)",
  "operating_hours": "object (optional; see Restaurant payload operating_hours shape; timeslots must not overlap per day)",
  "year_established": "integer (optional, nullable; 1800 to current year + 1)"
}
```

**Response (201):**
```json
{
  "message": "Restaurant created.",
  "data": { restaurant payload }
}
```

**Errors:**
- **422** – Validation (e.g. invalid fields).
- **403** – Free-tier limit: user already has one restaurant. Body: `{ "message": "Free tier allows one restaurant. Upgrade to add more." }`.

---

### Update restaurant

| Method | Path | Auth |
|--------|------|------|
| PUT | `/api/restaurants/{uuid}` | Bearer + verified |
| PATCH | `/api/restaurants/{uuid}` | Bearer + verified |

**Body (JSON):** Same fields as create (except **slug**); all optional. Send only fields to change. **Slug cannot be changed** after create (subdomain stability); the API does not accept `slug` on update. **template**: optional; must be one of the allowed template IDs (e.g. `template-1`, `template-2`). **primary_color** may be set to a hex value (e.g. `#ff5500`) or `null` to clear. **operating_hours**: optional; when present, same shape as in the restaurant payload; timeslots must not overlap per day; send `null` to clear. **year_established**: optional integer, nullable; when present must be 1800 to current year + 1; send `null` to clear.

**Response (200):** `{ "message": "Restaurant updated.", "data": { restaurant payload } }`  
**403:** Not owner. **404:** Not found. **422:** Validation.

---

### Delete restaurant

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/restaurants/{uuid}` | Bearer + verified |

**Response (204):** No content.  
**403:** Not owner. **404:** Not found.

---

### Upload logo

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{uuid}/logo` | Bearer + verified |

**Body:** `multipart/form-data` with field **`file`**. Allowed: image only (jpeg, jpg, png, gif, webp). Max size: **2MB**. Validation errors return **422**; the API returns a clear message for file too large (e.g. "The image must not be greater than 2MB.").  
**Storage:** The stored filename is server-derived from the file’s MIME type (e.g. `logo.jpg`, `logo.png`). The client-provided filename/extension is not used. **Image optimization:** On upload, the image is resized to fit within **300×300 px** (proportional; no cropping). If the image is already within those dimensions, it is stored as-is.

**Response (200):**
```json
{
  "message": "Logo updated.",
  "data": { restaurant payload }
}
```

**403:** Not owner. **404:** Restaurant not found. **422:** Validation (file type/size) or image processing errors (invalid/corrupt image, resize failure); message and errors.file indicate the reason.

---

### Upload banner

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{uuid}/banner` | Bearer + verified |

**Body:** Same as logo – `multipart/form-data`, field **`file`** (image: jpeg, png, gif, webp; max 2MB). Same validation and error messages. Stored filename is server-derived from MIME (e.g. `banner.jpg`), not from the client filename. **Image optimization:** On upload, the image is resized to fit within **1920×600 px** (proportional; no cropping). If the image is already within those dimensions, it is stored as-is.

**Response (200):** `{ "message": "Banner updated.", "data": { restaurant payload } }`  
**403:** Not owner. **404:** Restaurant not found. **422:** Validation (file type/size) or image processing errors (invalid/corrupt image, resize failure); message and errors.file indicate the reason.

---

### Serve logo (public)

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{uuid}/logo` | No (public) |

Serves the restaurant logo image. **No authentication required.** Response has the correct **Content-Type** (e.g. `image/jpeg`, `image/png`) so it can be used directly as `<img src="…/api/restaurants/{uuid}/logo">`.  
**404:** Restaurant or file not found.

---

### Serve banner (public)

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{uuid}/banner` | No (public) |

Serves the restaurant banner image. **No authentication required.** Response has the correct **Content-Type** for the image. **404:** Restaurant or file not found.

---

### Contact & links (restaurant contacts)

A restaurant can have **unlimited contact and link entries** (phone numbers and social/website URLs). The API path remains `/api/restaurants/{restaurant}/contacts` for backward compatibility. Each entry has a **type**, a **value** (phone number or URL), optional **label**, and **is_active** (only active entries are shown on the public site). All endpoints require **Bearer + verified** and restaurant ownership. Path parameters: **restaurant** and **contact** are **uuid** (never internal `id`). **No internal `id` in any response.**

**Phone-like types** (value = phone number): `whatsapp`, `mobile`, `phone`, `fax`, `other`.  
**Link types** (value = URL): `facebook`, `instagram`, `twitter`, `website`.

#### Contact payload (owner list/show/create/update)

```ts
{
  uuid: string;
  type: string;        // one of: whatsapp, mobile, phone, fax, other, facebook, instagram, twitter, website
  value: string;       // phone number (phone types) or URL (link types)
  number: string | null;  // backward compat: same as value for phone types; null for link types
  label: string | null;
  is_active: boolean;
  created_at: string;  // ISO 8601
  updated_at: string;  // ISO 8601
}
```

#### List contacts

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/contacts` | Bearer + verified |

**Response (200):** `{ "data": [ { contact payload } ] }`  
**404:** Restaurant not found or not owned.

#### Show contact

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/contacts/{contact}` | Bearer + verified |

**Response (200):** `{ "data": { contact payload } }`  
**404:** Restaurant or contact not found, or not owned.

#### Create contact

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/contacts` | Bearer + verified |

**Body (JSON):**
```json
{
  "type": "string (required; one of: whatsapp, mobile, phone, fax, other, facebook, instagram, twitter, website)",
  "value": "string (required; phone number or URL; max 500; for link types must be valid URL)",
  "number": "string (optional, backward compat; if value omitted, used as value for phone types)",
  "label": "string (optional, max 100)",
  "is_active": "boolean (optional, default true)"
}
```

**Validation:** For **link types** (`facebook`, `instagram`, `twitter`, `website`), **value** must be a valid URL. For **phone types**, **value** must be present and non-empty (max 500). **type** must be one of the allowed list.

**Response (201):** `{ "message": "Contact created.", "data": { contact payload } }`  
**404:** Restaurant not found or not owned. **422:** Validation (e.g. invalid type, missing value, link type with non-URL value).

#### Update contact

| Method | Path | Auth |
|--------|------|------|
| PATCH | `/api/restaurants/{restaurant}/contacts/{contact}` | Bearer + verified |
| PUT | `/api/restaurants/{restaurant}/contacts/{contact}` | Bearer + verified |

**Body (JSON):** Send only fields to change. All optional.
```json
{
  "type": "string (optional; one of: whatsapp, mobile, phone, fax, other, facebook, instagram, twitter, website)",
  "value": "string (optional, max 500; for link types must be valid URL)",
  "number": "string (optional, backward compat)",
  "label": "string (optional, nullable, max 100)",
  "is_active": "boolean (optional)"
}
```

**Response (200):** `{ "message": "Contact updated.", "data": { contact payload } }`  
**404:** Restaurant or contact not found, or not owned. **422:** Validation (e.g. link type with non-URL value).

#### Delete contact

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/restaurants/{restaurant}/contacts/{contact}` | Bearer + verified |

**Response (204):** No content.  
**404:** Restaurant or contact not found, or not owned.

---

### Public restaurant by slug (optional auth context)

Returns public restaurant data and menu items for subdomain or `/r/:slug` pages. Authentication is **not required**; guests can always use this endpoint. If a valid Bearer token is provided, the response includes viewer ownership metadata (`viewer.is_owner`, `viewer.owner_admin_url`) so the frontend can detect when the current viewer owns the restaurant. Only menu items whose **type** is `simple`, `combo`, or `with_variants` are included (items with `type` null are treated as simple; any future type is excluded). Menu items are also required to be **is_active** and (uncategorized or in a category with `is_active` true). Items with **is_available** false are still included; the frontend should show a "Not Available" indicator for them. **No internal `id` in any field.**

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/public/restaurants/{slug}` | No (optional Bearer for viewer context) |

**Query:** `locale` (optional). If provided and installed for the restaurant, description and menu item names/descriptions use that locale; otherwise the restaurant’s default locale is used.

**Response (200):**
```json
{
  "data": {
    "name": "string",
    "tagline": "string | null",
    "primary_color": "string | null",
    "slug": "string",
    "template": "string",
    "year_established": "number | null",
    "logo_url": "string | null",
    "banner_url": "string | null",
    "default_locale": "string",
    "currency": "string",
    "operating_hours": "object | null",
    "languages": ["en", "nl", ...],
    "locale": "string",
    "description": "string | null",
    "menu_items": [
      {
        "uuid": "string",
        "type": "simple | combo | with_variants",
        "name": "string",
        "description": "string | null",
        "price": "number | null",
        "sort_order": "number",
        "is_available": "boolean",
        "availability": "object | null",
        "tags": [{ "uuid": "string", "color": "string", "icon": "string", "text": "string" }, ...],
        "image_url": "string | null",
        "combo_entries": "always present when type is combo (array of combo entry objects)",
        "variant_option_groups": "optional; only when type is with_variants",
        "variant_skus": "optional; only when type is with_variants"
      }
    ],
    "menu_groups": [
      {
        "category_name": "string",
        "category_uuid": "string | null",
        "availability": "object | null",
        "image_url": "string | null",
        "items": [
          {
            "uuid": "string",
            "type": "simple | combo | with_variants",
            "name": "string",
            "description": "string | null",
            "price": "number | null",
            "is_available": "boolean",
            "availability": "object | null",
            "tags": [...],
            "image_url": "string | null",
            "combo_entries": "always present when type is combo (array of combo entry objects)",
            "variant_option_groups": "optional when type is with_variants",
            "variant_skus": "optional when type is with_variants"
          }
        ]
      }
    ],
    "feedbacks": [
      { "uuid": "string", "rating": "number (1-5)", "text": "string", "name": "string", "created_at": "string (ISO 8601)" }
    ],
    "contacts": [
      { "uuid": "string", "type": "string", "value": "string", "number": "string | null", "label": "string | null" }
    ],
    "viewer": {
      "is_owner": "boolean",
      "owner_admin_url": "string | null"
    }
  }
}
```

**contacts** (Contact & links) contains only **active** entries (is_active true); inactive are excluded. Each has **uuid**, **type**, **value** (phone number or URL), **number** (same as value for phone types; null for link types, for backward compat), and **label**. **template** is always one of `template-1` or `template-2` (legacy values `default` and `minimal` are normalized before returning; invalid values fall back to `template-1`).

**menu_groups** mirrors the Blade public page: categories sorted by sort_order; uncategorized items appear in a group with `category_name` "Menu" and `category_uuid` null. Each group has **category_name**, **category_uuid** (nullable), **availability** (object | null; same shape as operating_hours; from the category when category_uuid is non-null, null for uncategorized), **image_url** (full URL to category image or null; requires category to have image_path and menu relation), and **items** (menu item payloads with same shape as **menu_items** below, minus **sort_order**). **menu_items** remains the flat list for backward compatibility. Each menu item (in **menu_items** and in **menu_groups**[].**items**) includes **image_url** (full URL to the item image, or null). **Item image_url:** When the restaurant menu item has its own image (`image_path`), the URL points to `GET /api/restaurants/{restaurant}/menu-items/{item}/image`, which serves that image. When the item has no image but is linked to a catalog item (`source_menu_item_uuid`) and the catalog item has an image, the same URL is returned and the serve endpoint returns the catalog item’s image (fallback). **Variant image_url:** For **variant_skus**, **image_url** is the full URL to `GET /api/restaurants/{restaurant}/menu-items/{item}/variants/{sku}/image`. The serve endpoint returns the restaurant variant’s image when present, or the catalog source variant’s image when the item uses catalog variants and the variant has an image (fallback).

**Menu item type and optional fields:**
- **type** is always one of `simple`, `combo`, or `with_variants`. Restaurant items that are a single "ending variant" (linked to one catalog variant via `source_variant_uuid`) are exposed as **type** `simple` with one name and one price (no variant blocks).
- When **type** is **combo**, each item includes **combo_entries**: array of `{ "referenced_item_uuid": "string", "name": "string", "quantity": number, "modifier_label": "string | null", "variant_uuid": "string | null" }`. **name** is the referenced item's name in the requested locale (and variant label if the entry references a specific variant). Only UUIDs are used; no internal id.
- When **type** is **with_variants**, each item includes **variant_option_groups**: array of `{ "name": "string", "values": ["string", ...] }` and **variant_skus**: array of `{ "uuid": "string", "option_values": { "GroupName": "value", ... }, "price": number, "image_url": "string | null" }`. Option groups define choices (e.g. Size, Type); each SKU is one combination with a price; **image_url** is the full URL to the variant image (or null). For restaurant items that use a catalog item with variants, variant data (including **image_url** when the catalog variant has an image) comes from the catalog when the restaurant item has no own groups/SKUs. The same serve URL is used; the backend serves the catalog variant’s image when the restaurant item has no own variant image.

`operating_hours` has the same shape as in the restaurant payload (see **Operating hours shape** above); `null` when not set. Each **menu_item** includes **availability** (same type as operating_hours; `null` = always available). **feedbacks** contains only **approved** feedbacks (owner approves via PATCH); no internal `id` in any field.

**viewer metadata:**
- **Guests or non-owner users:** `viewer.is_owner = false`, `viewer.owner_admin_url = null`.
- **Authenticated owner viewer:** `viewer.is_owner = true` and `viewer.owner_admin_url` contains the frontend admin/manage URL for that restaurant (used to guide owner edits from public pages).
- Invalid/missing Bearer tokens do not break this endpoint for public use; it still returns the normal public payload.

**404:** Restaurant not found for the given slug.

---

## Legal content (Terms of Service, Privacy Policy)

Used on auth pages (e.g. register) to show Terms of Service and Privacy Policy in a modal. Content is editable by the superadmin. Supported locales: **en** (English), **es** (Spanish), **ar** (Arabic). Default fallback is **en**.

### Public (no auth)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/legal/terms` | No | Returns Terms of Service content (HTML string) for the requested locale. |
| GET | `/api/legal/privacy` | No | Returns Privacy Policy content (HTML string) for the requested locale. |

**Query parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `locale` | string | No | Locale code: `en`, `es`, or `ar`. If missing or invalid, falls back to `en`. |

**Response (200):**
```json
{
  "data": {
    "content": "string (HTML or plain text; may be empty)"
  }
}
```

### Superadmin (Bearer + verified + superadmin)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/superadmin/legal` | Bearer + superadmin | Get Terms of Service and Privacy Policy for all locales (en, es, ar). |
| PUT / PATCH | `/api/superadmin/legal` | Bearer + superadmin | Update Terms of Service and/or Privacy Policy per locale (one or all locales). |

**GET response (200):**
```json
{
  "data": {
    "en": {
      "terms_of_service": "string",
      "privacy_policy": "string"
    },
    "es": {
      "terms_of_service": "string",
      "privacy_policy": "string"
    },
    "ar": {
      "terms_of_service": "string",
      "privacy_policy": "string"
    }
  }
}
```

**PUT/PATCH body:** Per-locale objects. Each locale is optional; include only locales you want to update.

```json
{
  "en": {
    "terms_of_service": "string (optional, nullable)",
    "privacy_policy": "string (optional, nullable)"
  },
  "es": {
    "terms_of_service": "string (optional, nullable)",
    "privacy_policy": "string (optional, nullable)"
  },
  "ar": {
    "terms_of_service": "string (optional, nullable)",
    "privacy_policy": "string (optional, nullable)"
  }
}
```

**Response (200):**
```json
{
  "message": "Legal content updated.",
  "data": {
    "en": { "terms_of_service": "string", "privacy_policy": "string" },
    "es": { "terms_of_service": "string", "privacy_policy": "string" },
    "ar": { "terms_of_service": "string", "privacy_policy": "string" }
  }
}
```

**Errors:** 403 if not superadmin. No internal `id` in any response.

---

## Public restaurant page (HTML / SEO)

The public restaurant page is **subdomain-only**. Laravel serves it as HTML for crawlers and direct visits, with meta tags and semantic sections. The Vue app mounts on the same page and fetches data from GET `/api/public/restaurants/{slug}`.

| Where | Auth |
|--------|------|
| **GET** `http://{slug}.RESTAURANT_DOMAIN/` (e.g. `http://test.rms.local/` or `https://pizza.menus.example.com/`) | No |

- **Subdomain-only:** No `/r/{slug}` path. Configure `RESTAURANT_DOMAIN` (e.g. `rms.local` for local; `menus.example.com` in production). Add `*.rms.local` to your hosts file for local dev. The response is **full server-rendered HTML (Blade)** from `resources/views/generic-templates/template-1.blade.php` or `template-2.blade.php`: hero, menu (by category), about, reviews, and meta tags. The restaurant’s **template** (`template-1` or `template-2`) selects the layout; no Vue on the public page.
- **No internal `id`:** Only `uuid` and public identifiers are used in payloads and view data.

**404:** Restaurant not found for the given slug.

---

## Redirects

These routes live on the **main app domain** (e.g. `https://yourapp.com`), not under `/api`. They perform HTTP redirects for QR codes and similar flows. No internal `id` is used; only **uuid** in the path and for lookup.

### QR code → subdomain (restaurant page)

| Method | Path | Auth |
|--------|------|------|
| GET | `/page/r/{uuid}` | No |

**Purpose:** QR codes generated by the frontend use this URL so that scanning redirects to the restaurant’s public subdomain. The path parameter is the restaurant’s **uuid** (public identifier), not slug.

**Behavior:**

- **Found:** Redirects **302** to `{scheme}://{slug}.{restaurant_domain}/` (e.g. `https://pizza.rms.local/` or `https://pizza.menus.example.com/`). Scheme is **https** in production, otherwise from the request. `restaurant_domain` comes from `config('app.restaurant_domain')` (e.g. `RESTAURANT_DOMAIN` in `.env`).
- **Not found:** If no restaurant exists for the given **uuid**, returns **404**.

**Example:** `GET https://yourapp.com/page/r/550e8400-e29b-41d4-a716-446655440000` → 302 to `https://pizza.yourapp.com/` (when slug is `pizza` and restaurant_domain is `yourapp.com`).

---

## Feedbacks

Customers (guests, no auth) can submit feedback for a restaurant. Owners (Bearer + verified) list, approve/reject, or delete feedbacks. Only **approved** feedbacks appear on the public restaurant page and in GET `/api/public/restaurants/{slug}`.

**No internal `id` in any response;** only `uuid` is used.

### Feedback payload (owner list/update)

Returned by owner list, update, and by public submit (create) response:

```ts
{
  uuid: string;
  rating: number;        // 1-5
  text: string;
  name: string;
  is_approved: boolean;
  created_at: string;   // ISO 8601
  updated_at: string;   // ISO 8601 (owner payloads only; public submit may omit)
}
```

### Submit feedback (public, no auth)

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| POST | `/api/public/restaurants/{slug}/feedback` | No | 10/min (per IP); 60/min in local) |

**Body (JSON):**
```json
{
  "rating": "integer (required, 1-5)",
  "text": "string (required, max 65535)",
  "name": "string (required, max 255)"
}
```

**Response (201):**
```json
{
  "message": "Thank you for your feedback.",
  "data": { "uuid", "rating", "text", "name", "is_approved", "created_at" }
}
```

**Errors:** **404** – Restaurant not found for slug. **422** – Validation (rating 1-5, text and name required). **429** – Too many requests.

---

### List feedbacks (owner)

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/feedbacks` | Bearer + verified |

**Response (200):** `{ "data": [ { feedback payload } ] }` (all feedbacks for the restaurant, newest first).  
**404:** Restaurant not found or not owned by user.

---

### Update feedback (approve/reject) (owner)

| Method | Path | Auth |
|--------|------|------|
| PATCH | `/api/restaurants/{restaurant}/feedbacks/{feedback}` | Bearer + verified |
| PUT | `/api/restaurants/{restaurant}/feedbacks/{feedback}` | Bearer + verified |

**Body (JSON):**
```json
{
  "is_approved": "boolean (required)"
}
```

**Response (200):** `{ "message": "Feedback updated.", "data": { feedback payload } }`  
**404:** Restaurant or feedback not found, or not owned.

---

### Delete feedback (owner)

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/restaurants/{restaurant}/feedbacks/{feedback}` | Bearer + verified |

**Response (204):** No content.  
**404:** Restaurant or feedback not found, or not owned.

---

## Menus

Each restaurant can have **multiple** menus (e.g. "Lunch", "Dinner", "Drinks"). **Active** menus are shown on the public website. You can call `POST /api/restaurants/{restaurant}/menus` repeatedly to create additional menus. All endpoints use **Bearer + verified** and require restaurant ownership. Path parameters use `uuid` (never internal `id`).

### Menu payload (common)

```ts
{
  uuid: string;
  name: string | null;   // resolved from default locale for backward compatibility
  is_active: boolean;
  sort_order: number;
  translations: Record<string, { name: string; description: string | null }>;  // locale -> { name, description }
  created_at: string;    // ISO 8601
  updated_at: string;
}
```

### List menus

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/menus` | Bearer + verified |

**Response (200):** `{ "data": [ { menu payload } ] }` (ordered by sort_order). **404:** Restaurant not found.

### Show menu

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/menus/{menu}` | Bearer + verified |

**Response (200):** `{ "data": { menu payload } }`. **404:** Restaurant or menu not found.

### Create menu

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/menus` | Bearer + verified |

**Body (JSON):**
```json
{
  "name": "string (optional, max 255; backward compat, maps to default locale)",
  "is_active": "boolean (optional, default true)",
  "sort_order": "integer (optional, min 0)",
  "translations": {
    "locale": { "name": "string (required when locale present, max 255)", "description": "string (optional, nullable)" }
  }
}
```

Locales in `translations` must be installed for the restaurant. **Response (201):** `{ "message": "Menu created.", "data": { menu payload } }`. **403:** Not owner. **404:** Restaurant not found. **422:** Validation or uninstalled locale(s).

### Update menu

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/restaurants/{restaurant}/menus/{menu}` | Bearer + verified |

**Body:** Same fields as create; all optional. `translations`: locale → `{ name?, description? }`; locales must be installed. **Response (200):** `{ "message": "Menu updated.", "data": { menu payload } }`. **403/404/422:** As above.

### Delete menu

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/restaurants/{restaurant}/menus/{menu}` | Bearer + verified |

**Response (204):** No content. **403/404:** As above.

### Reorder menus

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/menus/reorder` | Bearer + verified |

**Body (JSON):**
```json
{
  "order": ["uuid1", "uuid2", "..."]
}
```

**Response (200):** `{ "message": "Order updated." }`. **404:** Restaurant not found. **422:** Validation (order required, each element uuid).

---

## Categories

Categories belong to a menu (e.g. Appetizers, Mains). Name is translated per restaurant locale. All endpoints **Bearer + verified**, ownership via restaurant. Reorderable via reorder endpoint.

### Category payload (common)

```ts
{
  uuid: string;
  sort_order: number;
  is_active: boolean;  // when false, category and its items are hidden on the public menu
  availability: OperatingHours | null;  // same type as operating_hours; null = all available (menu del día)
  image_url: string | null;  // full URL to serve category image (512×512 square; restaurant context)
  translations: Record<string, { name: string; description: string | null }>;  // locale -> { name, description? }
  created_at: string;
  updated_at: string;
}
```

### List categories

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/menus/{menu}/categories` | Bearer + verified |

**Response (200):** `{ "data": [ { category payload } ] }`. **404:** Restaurant or menu not found.

### Show category

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}` | Bearer + verified |

**Response (200):** `{ "data": { category payload } }`. **404:** Not found.

### Create category

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/menus/{menu}/categories` | Bearer + verified |

**Body (JSON):**
```json
{
  "sort_order": "integer (optional, min 0)",
  "availability": "object | null (optional; same shape as operating_hours; null = all available; timeslots must not overlap per day)",
  "translations": {
    "locale": { "name": "string (required with translations, max 255)", "description": "string (optional, nullable)" }
  }
}
```

Translations must use locales installed for the restaurant. **Response (201):** `{ "message": "Category created.", "data": { category payload } }`. **422:** Uninstalled locale(s) or invalid availability.

### Update category

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}` | Bearer + verified |

**Body:** sort_order, is_active (optional boolean), **availability** (optional; same shape as operating_hours; send **null** to clear / set "all available"; timeslots must not overlap per day), translations (optional; each locale: name, description optional). **Response (200):** `{ "message": "Category updated.", "data": { category payload } }`.

### Delete category

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}` | Bearer + verified |

**Response (204):** No content.

### Category image (restaurant context)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}/image` | Bearer + verified |
| GET | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}/image` | No (public, for `<img src>`) |
| DELETE | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}/image` | Bearer + verified |

**Upload (POST):** Request body **multipart/form-data** with field **file**. Image only (jpeg, png, gif, webp), max 2MB. Image is resized/cropped to 512×512 (center-crop to square). User must own the restaurant. **Response (200):** `{ "message": "Image updated.", "data": { category payload } }`. **422:** Validation; **403:** Not owner. **Serve (GET):** Returns the image file (no auth). **Delete (DELETE):** Clears the category image. **Response (200):** `{ "message": "Image removed.", "data": { category payload } }`.

### Reorder categories

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/menus/{menu}/categories/reorder` | Bearer + verified |

**Body (JSON):** `{ "order": ["uuid1", "uuid2", ...] }`. **Response (200):** `{ "message": "Order updated." }`.

---

## Menu item tags

Tags (e.g. "Spicy", "Vegan") can be attached to restaurant menu items. Only **default (system) tags** exist; custom tag create/update/delete are disabled (POST/PATCH/DELETE return 403). All tag endpoints use **Bearer + verified**. **No internal `id` in any response;** only `uuid` is used.

### Tag payload (common)

```ts
{
  uuid: string;
  color: string;   // e.g. hex #dc2626 or color name
  icon: string;   // e.g. Material icon name
  text: string;   // label, e.g. "Spicy", "Vegan"
  is_default?: boolean;  // true for system tags (read-only); false for user's custom tags
}
```

### List tags

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/menu-item-tags` | Bearer + verified |

Returns **default (system) tags only**. Custom tag creation is disabled; only default tags exist.

**Response (200):**
```json
{
  "data": [ { "uuid": "string", "color": "string", "icon": "string", "text": "string", "is_default": true }, ... ]
}
```

---

### Create tag (disabled)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/menu-item-tags` | Bearer + verified |

**Response (403):** Custom tag creation is not available. Body: `{ "message": "Custom menu item tags are not available. Use the default tags." }`.

---

### Update tag (disabled)

| Method | Path | Auth |
|--------|------|------|
| PATCH | `/api/menu-item-tags/{tag}` | Bearer + verified |
| PUT | `/api/menu-item-tags/{tag}` | Bearer + verified |

**Response (403):** Tag updates are not available. Body: `{ "message": "Custom menu item tags are not available. Use the default tags." }`.

---

### Delete tag (disabled)

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/menu-item-tags/{tag}` | Bearer + verified |

**Response (403):** Tag deletion is not available. Body: `{ "message": "Custom menu item tags are not available. Use the default tags." }`.

---

## Menu items (user-level / catalog)

List, create, and manage **catalog** menu items from the standalone “Menu items” page. Catalog items are standalone (not tied to any restaurant). They can be added to restaurant categories as references (via the restaurant menu-items API). All endpoints **Bearer + verified**. **No internal `id` in any response;** only `uuid` (and variant_skus[].uuid) are used.

### User-level menu item payload

All catalog menu item payloads include:

```ts
{
  uuid: string;
  category_uuid: string | null;
  sort_order: number;
  type: 'simple' | 'combo' | 'with_variants';
  price: number | null;        // effective: simple = base price; combo = combo_price if set; with_variants = null
  image_url: string | null;    // full URL to serve image (catalog context only; 512×512 square)
  translations: Record<string, { name: string; description: string | null }>;
  created_at: string;
  updated_at: string;
  restaurant_uuid?: string | null;  // null for standalone catalog items
}
```

- **When `type` is `combo`:** payload also includes:
  - **combo_price**: number | null (optional combo-level price).
  - **combo_entries**: array of `{ menu_item_uuid: string; variant_uuid?: string | null; quantity: number; modifier_label?: string | null }`. Each entry references another catalog menu item (by uuid); if that item has variants, **variant_uuid** must be the uuid of a variant_sku of that item. Referenced items must be owned by the same user.

- **When `type` is `with_variants`:** payload also includes:
  - **variant_option_groups**: array of `{ name: string; values: string[] }` (ordered option groups, e.g. Type: ["Hawaiian", "Pepperoni"], Size: ["Small", "Family"]).
  - **variant_skus**: array of `{ uuid: string; option_values: Record<string, string>; price: number; image_url?: string | null }`. Each SKU is one combination of option values (Cartesian product) with its own price; **image_url** is optional (variant image upload may be a follow-up).

For GET `/api/menu-items` list, all returned items are standalone (`restaurant_uuid` null).

### List all my menu items (catalog only)

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/menu-items` | Bearer + verified |

**Response (200):** `{ "data": [ { user-level menu item payload } ] }`. Returns **only standalone (catalog) items** owned by the user. Restaurant menu items (including references to catalog items) are not included; use GET `/api/restaurants/{restaurant}/menu-items` for per-restaurant lists. Order: by category then sort_order.

### Show one menu item

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/menu-items/{item}` | Bearer + verified |

**Response (200):** `{ "data": { user-level menu item payload } }`. **404:** Not found or not owned.

### Create standalone menu item

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/menu-items` | Bearer + verified |

**Body (JSON):**
```json
{
  "sort_order": "integer (optional, min 0)",
  "price": "number (optional, min 0; only for type simple)",
  "type": "string (optional: simple | combo | with_variants, default simple)",
  "combo_price": "number (optional, min 0; only for type combo)",
  "translations": {
    "locale": { "name": "string (required)", "description": "string | null (optional)" }
  },
  "combo_entries": "array (required when type combo): [{ menu_item_uuid, variant_uuid?, quantity?, modifier_label? }]",
  "variant_option_groups": "array (required when type with_variants): [{ name, values: string[] }]",
  "variant_skus": "array (required when type with_variants): [{ option_values: object, price, image_url? }]"
}
```

- At least one translation with a non-empty **name** is required.
- **Combo:** `combo_entries` must have at least one entry; each **menu_item_uuid** must be a catalog menu item owned by the user. When the referenced item has variants, **variant_uuid** (uuid of that item’s variant_sku) is required.
- **With variants:** `variant_option_groups` must have at least one group, each with **name** and non-empty **values**. **variant_skus** must cover exactly the Cartesian product of all option groups (one SKU per combination), each with **price** (required) and optional **image_url**.

**Response (201):** `{ "message": "Menu item created.", "data": { user-level menu item payload } }`. **422:** Validation (e.g. missing name, invalid combo_entries or variant_skus).

### Update menu item

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/menu-items/{item}` | Bearer + verified |

**Body:** sort_order, price (optional, for simple), type (optional), combo_price (optional, for combo), translations (optional), combo_entries (optional, for combo; replaces all entries), variant_option_groups and variant_skus (optional, for with_variants; both required together; replace all). When changing type, the appropriate data (combo_entries or variant_option_groups + variant_skus) must be sent and validated as for create. **Response (200):** `{ "message": "Menu item updated.", "data": { user-level menu item payload } }`. **404:** Not found or not owned. **422:** Validation.

### Delete menu item

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/menu-items/{item}` | Bearer + verified |

**Response (204):** No content. **404:** Not found or not owned.

### Catalog menu item image (simple/combo: one image per item)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/menu-items/{item}/image` | Bearer + verified |
| GET | `/api/menu-items/{item}/image` | No (public, for `<img src>`) |
| DELETE | `/api/menu-items/{item}/image` | Bearer + verified |

**Upload (POST):** Request body **multipart/form-data** with field **file**. Image only (jpeg, png, gif, webp), max 2MB. Image is resized/cropped to 512×512 (center-crop to square). Only available for **standalone (catalog) menu items** (not restaurant-scoped items). **Response (200):** `{ "message": "Image updated.", "data": { user-level menu item payload } }`. **404:** Not found or item is not standalone. **422:** Validation (e.g. file too large, wrong type). **403:** Not owner.

**Serve (GET):** Returns the image file (no auth). **404:** Item or file not found.

**Delete (DELETE):** Clears the catalog menu item image. **Response (200):** `{ "message": "Image removed.", "data": { user-level menu item payload } }`.

### Catalog menu item variant SKU image (type with_variants)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/menu-items/{item}/variants/{sku}/image` | Bearer + verified |
| GET | `/api/menu-items/{item}/variants/{sku}/image` | No (public, for `<img src>`) |
| DELETE | `/api/menu-items/{item}/variants/{sku}/image` | Bearer + verified |

**Upload (POST):** Same validation as catalog menu item image (multipart file, 512×512). **Response (200):** `{ "message": "Image updated.", "data": { uuid, option_values, price, image_url } }`. **404:** Item or variant not found. **Serve (GET)** and **Delete (DELETE)** as above.

---

## Menu items (restaurant-scoped)

CRUD and reorder for items (name + description per locale) within a restaurant. Locales must be installed for the restaurant. Optional **category_uuid** (category must belong to the restaurant). All endpoints **Bearer + verified**.

### Menu item payload (common)

All menu item payloads include:

```ts
{
  uuid: string;
  category_uuid: string | null;
  sort_order: number;
  is_active: boolean;     // when false, item is hidden on the public menu
  is_available: boolean;  // when false, item is shown but marked "Not Available" on the public menu
  availability: OperatingHours | null;  // same type as operating_hours; null = all available
  price: number | null;   // effective price (base or override)
  translations: Record<string, { name: string; description: string | null }>;  // effective per locale
  tags: Array<{ uuid: string; color: string; icon: string; text: string }>;  // menu item tags attached to this item
  image_url: string | null;  // full URL to serve image (simple/combo: one image per item); null when no image
  created_at: string;
  updated_at: string;
}
```

When the item has **type** `with_variants`, the payload also includes **variant_skus**: `Array<{ uuid: string; option_values: object; price: number; image_url: string | null }>`. Each variant’s **image_url** is the full URL to serve that variant’s image (null when none). No internal `id` in any variant.

When the item is a **restaurant usage of a catalog item** (added from “Menu items” into a restaurant category), the payload also includes:

```ts
{
  source_menu_item_uuid: string;
  source_variant_uuid?: string | null; // when added from a with_variants catalog item: uuid of the variant_sku (ending variant)
  price_override: number | null;      // null = use base price (or variant price when source_variant_uuid set)
  translation_overrides: Record<string, { name?: string; description?: string | null }>;
  base_price: number | null;          // catalog base price, or variant price when source_variant_uuid set
  base_translations: Record<string, { name: string; description: string | null }>;
  has_overrides: boolean;             // true if any override is set (for “Revert to base” UI)
}
```

When **source_variant_uuid** is present, **name** in `translations` is the catalog base name plus the variant's option_values label (e.g. "Burger - Hawaiian, Small"), and **price** / **base_price** come from that variant. No internal `id` is exposed anywhere.

### List menu items

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/menu-items` | Bearer + verified |

**Response (200):** `{ "data": [ { menu item payload } ] }` (ordered by category then sort_order).

### Show menu item

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/restaurants/{restaurant}/menu-items/{item}` | Bearer + verified |

**Response (200):** `{ "data": { menu item payload } }`. **404:** Not found.

### Create menu item

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/menu-items` | Bearer + verified |

**Body (JSON):** Either create from catalog or create a new item:

- **Add from catalog:** `source_menu_item_uuid` (required), optional **source_variant_uuid** (see validation below), `category_uuid`, `sort_order`, optional `price_override`, `translation_overrides` (per-locale name/description). Catalog item must be a standalone menu item owned by the user. No `translations` needed; effective values come from base + overrides (and from the chosen variant when **source_variant_uuid** is set).
- **Create new item:** `category_uuid`, `sort_order`, optional `price`, and `translations` (required; locales must be installed for the restaurant).

**Validation when adding from catalog:**
- If the catalog item has **type** `with_variants`: **source_variant_uuid** is **required** and must be the `uuid` of one of that item’s **variant_skus** (only “ending” variants are addable; the base item is not).
- If the catalog item is **simple** or **combo**: **source_variant_uuid** must be null or absent (422 if sent).

```json
{
  "category_uuid": "string (optional)",
  "sort_order": "integer (optional, min 0)",
  "availability": "object | null (optional; same shape as operating_hours; null = all available; timeslots must not overlap per day)",
  "source_menu_item_uuid": "string (optional, uuid of catalog item; when set, adds catalog item to category)",
  "source_variant_uuid": "string (optional, uuid of catalog item's variant_sku; required when catalog type is with_variants; must be absent when simple/combo)",
  "price_override": "number (optional, min 0; only when source_menu_item_uuid set)",
  "translation_overrides": { "locale": { "name": "string", "description": "string | null" } },
  "price": "number (optional, min 0; only when creating new item)",
  "translations": {
    "locale": { "name": "string (required with translations)", "description": "string | null (optional)" }
  },
  "tag_uuids": "array (optional): UUIDs of default tags to attach; only default tag UUIDs allowed; 422 if invalid"
}
```

**Response (201):** `{ "message": "Menu item created.", "data": { menu item payload } }`. **422:** Uninstalled locale(s), source_variant_uuid validation, invalid availability, or invalid tag_uuids. **403:** Catalog item not found or not owned.

### Update menu item

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/restaurants/{restaurant}/menu-items/{item}` | Bearer + verified |

**Body:** category_uuid, sort_order, **is_active** (optional boolean; when false, item is hidden on the public menu), **is_available** (optional boolean; when false, item is shown on the public menu but marked "Not Available"), **availability** (optional; same shape as operating_hours; send **null** to clear / set "all available"; timeslots must not overlap per day), translations (for items without source), price (for items without source), price_override, translation_overrides (for items with source), **revert_to_base** (boolean; when true, clears price_override and translation_overrides so effective values revert to catalog base), **tag_uuids** (optional array of UUIDs; replaces item's tags; only default tag UUIDs allowed; 422 if invalid). **Response (200):** `{ "message": "Menu item updated.", "data": { menu item payload } }`.

### Delete menu item

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/restaurants/{restaurant}/menu-items/{item}` | Bearer + verified |

**Response (204):** No content.

### Menu item image (simple/combo: one image per item)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/menu-items/{item}/image` | Bearer + verified |
| GET | `/api/restaurants/{restaurant}/menu-items/{item}/image` | No (public, for `<img src>`) |
| DELETE | `/api/restaurants/{restaurant}/menu-items/{item}/image` | Bearer + verified |

**Upload (POST):** Request body **multipart/form-data** with field **file**. Image only (jpeg, png, gif, webp), max 2MB. Image is resized/cropped to 512×512 (center-crop to square). User must own the restaurant. **Response (200):** `{ "message": "Image updated.", "data": { menu item payload } }`. **422:** Validation (e.g. file too large, wrong type); **403:** Not owner.

**Serve (GET):** Returns the image file (no auth). When the restaurant item has no image but is linked to a catalog item that has an image, the catalog image is served. **404:** Restaurant, item, or file not found.

**Delete (DELETE):** Clears the menu item image. **Response (200):** `{ "message": "Image removed.", "data": { menu item payload } }`. **404:** Menu item not found; **403:** Not owner.

### Variant SKU image (menu items with type with_variants: one image per variant)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/menu-items/{item}/variants/{sku}/image` | Bearer + verified |
| GET | `/api/restaurants/{restaurant}/menu-items/{item}/variants/{sku}/image` | No (public, for `<img src>`) |
| DELETE | `/api/restaurants/{restaurant}/menu-items/{item}/variants/{sku}/image` | Bearer + verified |

**Upload (POST):** Request body **multipart/form-data** with field **file**. Same validation as menu item image (image only, jpeg/png/gif/webp, max 2MB; resized to 512×512). **Response (200):** `{ "message": "Image updated.", "data": { uuid, option_values, price, image_url } }` where **image_url** is the full serve URL. **404:** Menu item or variant not found; **422/403:** as above.

**Serve (GET):** Returns the variant image file (no auth). When the restaurant variant has no image but the item is linked to a catalog item whose variant (same uuid) has an image, the catalog variant image is served. **404:** Not found.

**Delete (DELETE):** Clears the variant image. **Response (200):** `{ "message": "Image removed.", "data": { uuid, option_values, price, image_url: null } }`.

### Reorder menu items (within category)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/categories/{category}/menu-items/reorder` | Bearer + verified |

**Body (JSON):** `{ "order": ["itemUuid1", "itemUuid2", ...] }`. **Response (200):** `{ "message": "Order updated." }`. **404:** Restaurant or category not found.

---

## Machine translation

The API does not perform translation itself; it is a client of an external service (e.g. LibreTranslate). It validates input, checks that requested languages are supported by the service, calls the service, and returns the response (or maps errors to HTTP status).

### List supported languages

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| GET | `/api/translate/languages` | Bearer + verified | 30/min (per user or IP) |

Returns the list of languages supported by the external translation service (proxied from the service’s languages endpoint, e.g. LibreTranslate `GET /languages`). Use this to know which `from_locale` and `to_locale` values are valid for `POST /api/translate`.

**Response (200):**
```json
{
  "data": [
    { "code": "en", "name": "English" },
    { "code": "es", "name": "Spanish" }
  ]
}
```

Each object may include optional `targets` (array of target language codes), depending on the service.

**503:** Translation service not configured or unavailable.  
**502:** Translation service error (e.g. languages endpoint unreachable or invalid response).

---

### Translate text

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| POST | `/api/translate` | Bearer + verified | 30/min (per user or IP) |

**Body (JSON):**
```json
{
  "text": "string (required)",
  "from_locale": "string (required, e.g. en)",
  "to_locale": "string (required, e.g. nl)"
}
```

`from_locale` and `to_locale` must be supported by the external service (see `GET /api/translate/languages`). The API validates this before calling the service.

**Response (200):**
```json
{
  "translated_text": "string"
}
```

**429:** Too many requests; wait before retrying.  
**503:** Translation service not configured or unavailable.  
**502:** Translation service error (e.g. LibreTranslate unreachable or returned an error).  
**422:** Validation (e.g. missing or invalid fields), or **source/target language not supported** by the service (`errors.from_locale` or `errors.to_locale`: "Language not supported.").

**Configuration (backend):** To enable machine translation, set `TRANSLATION_DRIVER=libre` and `LIBRE_TRANSLATE_URL` (e.g. `https://libre.example.com`). Optional: `LIBRE_TRANSLATE_API_KEY` for instances that require an API key. When not configured, the stub driver is used and the translate endpoints return **503**. Ensure the service has the required languages loaded (e.g. LibreTranslate `LT_LOAD_ONLY`); the API does not install languages.

The Settings UI uses **POST /api/translate** for "Translate from default" (restaurant description): it sends the default locale's description as `text` with `from_locale` and `to_locale`, then saves the returned `translated_text` to the restaurant translation for the target locale.

---

## Changelog

- **2026-02-28**: **Legal content: multilingual (en, es, ar).** Public **GET /api/legal/terms** and **GET /api/legal/privacy** accept optional query parameter **locale** (`en`, `es`, `ar`); fallback to `en` if missing or invalid. Response shape unchanged: `{ "data": { "content": "..." } }`. Superadmin **GET /api/superadmin/legal** returns all three locales: `{ "data": { "en": { "terms_of_service", "privacy_policy" }, "es": { ... }, "ar": { ... } } }`. **PATCH /api/superadmin/legal** accepts per-locale body so superadmin can update one or all locales. `site_legal` table now stores content per locale (e.g. `terms_of_service_en`, `privacy_policy_es`). No internal `id` in any response.
- **2026-02-28**: **Legal content (Terms of Service, Privacy Policy).** New **GET /api/legal/terms** and **GET /api/legal/privacy** (no auth) return HTML content for modals on auth pages. Superadmin: **GET /api/superadmin/legal** and **PUT/PATCH /api/superadmin/legal** to read and update both texts. New `site_legal` table (terms_of_service, privacy_policy columns). No internal `id` in any response.
- **2026-02-28**: **Owner dashboard stats.** New **GET /api/dashboard/stats** (Bearer + verified) returns counts for the authenticated owner: **restaurants_count**, **menu_items_count** (catalog/standalone only), **feedbacks_total**, **feedbacks_approved**, **feedbacks_rejected**. Used by the owner dashboard to display Menu items, Restaurants, and Feedbacks (total, approved, rejected). No internal `id` in response.
- **2026-02-18**: **Category list/show payload: fallback for blank translations.** When returning category payloads (list, show, create, update, image upload/delete), the API now fills blank **name** and **description** for any locale with the restaurant’s **default_locale** value, or the first non-empty value across locales. So when the default language is changed (e.g. to Spanish), categories that have no Spanish translation still return a displayable value (e.g. from English). Response shape unchanged; only empty values are replaced for display.
- **2026-02-28**: **Translation: API as client only; supported-languages check.** All translation logic lives in the external service (LibreTranslate); the API only validates input, calls the service, and returns responses. New **GET /api/translate/languages** (Bearer + verified, same rate limit as translate) returns the list of languages supported by the service (proxied from the service’s languages endpoint). **POST /api/translate** now checks `from_locale` and `to_locale` against that list before calling translate; returns **422** with `errors.from_locale` or `errors.to_locale` ("Language not supported.") when a locale is not supported. No internal `id` in any response.
- **2026-02-28**: **GET /api/public/restaurants/{slug}: optional owner viewer metadata.** Endpoint remains public (no required auth) but now accepts optional Bearer context. Response now includes `viewer` with `is_owner` and `owner_admin_url` (only set for authenticated owner viewers). Guests and non-owner viewers receive `is_owner: false` and `owner_admin_url: null`. No internal `id` exposed.
- **2026-02-27**: **GET /api/public/restaurants/{slug}: catalog image fallback for menu items and variants.** When a restaurant menu item has no own image but is linked to a catalog item (`source_menu_item_uuid`) that has an image, **image_url** is now set to the same restaurant-scoped URL and `GET /api/restaurants/{restaurant}/menu-items/{item}/image` serves the catalog item’s image. When variant data comes from the catalog and a catalog variant has an image, **variant_skus**[].**image_url** is set and `GET /api/restaurants/{restaurant}/menu-items/{item}/variants/{sku}/image` serves the catalog variant’s image. No new routes; behavior documented under Public restaurant by slug.
- **2026-02-27**: **GET /api/public/restaurants/{slug}: catalog combo type and combo_entries.** When a restaurant menu item is linked to a catalog item (`source_menu_item_uuid` set, no `source_variant_uuid`), the public API now uses the **source’s type** and **source’s combo_entries** for that item. So a catalog combo added to a restaurant is exposed as **type: combo** with **combo_entries** from the catalog, fixing items that previously appeared as type `simple` with no breakdown.
- **2026-02-27**: **GET /api/public/restaurants/{slug}: combo_entries, image_url.** For **combo** items, **combo_entries** is always present (array; empty if none); controller eager-loads `comboEntries` and nested relations. Every menu item (flat and in **menu_groups**[].**items**) includes **image_url** (full URL to restaurant-scoped item image or null). Each **menu_groups** entry includes **image_url** (category image full URL or null); category image requires `category.menu` to be loaded. Documented in response schema. No internal `id` in any response.
- **2026-02-27**: **Menu item images: catalog-only; category images in restaurant context.** **Catalog (menu items context):** Image upload for standalone menu items is available only in the catalog (Menu items) flow. New endpoints: POST/GET/DELETE `/api/menu-items/{item}/image` and POST/GET/DELETE `/api/menu-items/{item}/variants/{sku}/image`. User-level menu item payload now includes **image_url** (full URL or null); variant_skus include **image_url** (full URL when set). **Restaurant context:** Menu item image UI is not shown when editing a restaurant’s menu item; images for catalog items are managed in the Menu items (catalog) page. **Category images:** Categories support one image per category in the restaurant context. New column **image_path** on `categories`; new endpoints POST/GET/DELETE `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}/image`. Validation and resize (512×512 square) as for menu item images. Category payload now includes **image_url** (full URL or null). No internal `id` in any response.
- **2026-02-27**: **Images for menu items and variant SKUs.** Menu items (simple/combo) support one image per item: **image_path** on `menu_items`; stored under `restaurants/{restaurant_uuid}/menu-items/{item_uuid}/image`. Variant SKUs (type `with_variants`) support one image per SKU: path stored in existing **image_url** column on `menu_item_variant_skus`; stored under `restaurants/{restaurant_uuid}/menu-items/{item_uuid}/variants/{sku_uuid}/image`. Upload: POST multipart (file); validation as restaurant logo (image only, jpeg/png/gif/webp, max 2MB); server resizes to 512×512 (center-crop to square). Serve: GET (public, for `<img src>`). Delete: DELETE to clear image. New endpoints: POST/GET/DELETE `/api/restaurants/{restaurant}/menu-items/{item}/image`; POST/GET/DELETE `/api/restaurants/{restaurant}/menu-items/{item}/variants/{sku}/image`. Restaurant-scoped menu item payload now includes **image_url** (full URL or null) and, when type is `with_variants`, **variant_skus** with **image_url** per variant (full URL or null). Public GET `/api/public/restaurants/{slug}` menu items and variant_skus include **image_url** (full serve URL). No internal `id` in any response.
- **2026-02-27**: **Refresh-token auth (rotation).** `POST /api/login` now sets an HttpOnly refresh token cookie (`rms_refresh`). New `POST /api/auth/refresh` rotates the refresh token and returns a new access token (403 when email not verified or account deactivated); **401/403 clear the refresh cookie**. Refresh rotation tolerates near-parallel refresh calls via a short grace window (revoked token reuse only when rotated). `POST /api/logout` and `POST /api/logout-all` now also revoke refresh tokens (if present / all) and clear the refresh cookie. Cookie Secure is set when not local, or whenever SameSite is `none`. No internal `id` in any response.
- **2026-02-21**: **GET /api/public/restaurants/{slug}: menu_groups availability.** Each entry in **menu_groups** now includes **availability** (object | null; same shape as operating_hours). When the group has a category (category_uuid non-null), availability is the category’s availability; when uncategorized (category_uuid null), availability is null. No internal `id` in any response.
- **2026-02-21**: **Contact & links (restaurant contacts) refactor.** Module renamed conceptually to "Contact & links"; API path unchanged `/api/restaurants/{restaurant}/contacts`. Supports **phone types** (whatsapp, mobile, phone, fax, other) and **link types** (facebook, instagram, twitter, website). Unified **value** field (phone number or URL); **number** kept for backward compat (same as value for phone types, null for link types). Migration adds **value** column, backfills from number, makes number nullable. Validation: link types require valid URL in value; phone types require non-empty value (max 500). Owner payload and GET `/api/public/restaurants/{slug}` **contacts** now include **value** and **number**. No internal `id` in any response.
- **2026-02-21**: **Restaurant year established.** New optional field **year_established** (integer, nullable) on restaurants: migration adds column; valid range 1800–(current year + 1). Included in restaurant payload (list, show, create response, update, logo/banner upload). Create and update accept optional **year_established**; send `null` to clear on update. GET `/api/public/restaurants/{slug}` includes **year_established** so public sites can display "Est. 1995". No internal `id` in any response.
- **2026-02-21**: **QR redirect (backend).** New GET `/page/r/{uuid}` on the main app domain: looks up restaurant by **uuid** (not slug); if found, redirects 302 to `{scheme}://{slug}.{restaurant_domain}/`; if not found, returns 404. Used by frontend-generated QR codes. No internal `id` in any response. Documented under Redirects in this API reference.
- **2026-02-21**: **QR code value: redirect URL (manage view).** The restaurant manage view "Web address" card displays a QR code that encodes **`[domain]/page/r/{uuid}`** (restaurant **uuid**), not the subdomain URL. Scanning the QR hits GET `/page/r/{uuid}` on the main app domain, which redirects to the restaurant’s subdomain. The frontend builds the QR URL from `VITE_APP_URL` (or `window.location.origin`) + `/page/r/` + restaurant uuid. See **Redirects** for the backend endpoint.
- **2026-02-21**: **Public sites modal (View Menu):** The mobile sticky "View Menu" button, full-page modal with collapsible categories, menu items, tag tooltips (using `tags[].text`), and "Surprise me" (random item) use **existing** GET `/api/public/restaurants/{slug}` data only (**menu_groups**, **menu_items**, **tags**). No new or changed backend endpoints.
- **2026-02-21**: **Public sites: ending-variant names show "Base - Option label".** GET `/api/public/restaurants/{slug}` now eager-loads **sourceVariantSku** for restaurant items with `source_variant_uuid`. The effective **name** for those items is "Base name - Variant label" (e.g. "Burger - Large", "Burger - Medium") so the public menu does not repeat the base name only.
- **2026-02-21**: **GET /api/public/restaurants/{slug}: public sites menu scope (type, combo, variants).** Only menu items with **type** `simple`, `combo`, or `with_variants` are included (null treated as simple). Each menu item now includes **type** (`simple` | `combo` | `with_variants`). For **combo** items: **combo_entries** with `referenced_item_uuid`, `name` (locale-resolved), `quantity`, `modifier_label`, `variant_uuid`. For **with_variants** items: **variant_option_groups** (name, values) and **variant_skus** (uuid, option_values, price, image_url); variant data from catalog when restaurant item has no own groups/SKUs. Restaurant items that are a single "ending variant" (`source_variant_uuid`) are exposed as **type** `simple` with one price/name. No internal `id` in any response.
- **2026-02-21**: **GET /api/public/restaurants/{slug}: menu_groups and template normalization.** Response now includes **menu_groups** (same shape as Blade public page: category_name, category_uuid nullable, items with uuid, name, description, price, is_available, tags; categories sorted; uncategorized as "Menu"). **menu_items** retained for backward compatibility. **template** is always returned as `template-1` or `template-2` (default/minimal normalized; invalid → template-1). No internal `id` in any response.
- **2026-02-18**: **Public page: generic-templates template-1 and template-2.** Public restaurant page is **full Blade** (no Vue): hero, menu by category, about, reviews rendered in `resources/views/generic-templates/template-1.blade.php` or `template-2.blade.php`. Two distinct layouts: **template-1** (warm, card-based, accent color, DM Sans) and **template-2** (minimal, flat, IBM Plex Sans). Config and Restaurant::TEMPLATES use `template-1` and `template-2`; migration maps legacy `default`→`template-1`, `minimal`→`template-2`. Create/update and payloads use template-1/template-2.
- **2026-02-18**: **Public restaurant page: subdomain-only (no /r/ path).** Public URLs are **subdomain-only** at `http://{slug}.RESTAURANT_DOMAIN/` (e.g. `http://test.rms.local/` or `https://pizza.menus.example.com/`). Path-based route `/r/{slug}` removed. Set `RESTAURANT_DOMAIN` (e.g. `rms.local` for local; add `*.rms.local` to hosts). Subdomain route is registered first so GET / on a subdomain serves the Blade HTML (meta tags, generic-templates); Vue mounts and fetches from GET `/api/public/restaurants/{slug}`. API reference and changelog updated accordingly.
- **2026-02-20**: **Restaurant template and public SEO page.** Restaurants: new **template** field (string, default `default`; valid values from `config('templates.ids')`, e.g. `default`, `minimal`). Migration adds `template` column. Create and update accept optional **template**. Restaurant payload (owner and public) and GET `/api/public/restaurants/{slug}` response include **template**. Two Blade layouts in `resources/views/generic-templates/` (default, minimal) yield sections: meta, header, main, footer. **Public HTML page:** served at `{slug}.RESTAURANT_DOMAIN` (subdomain-only) as Blade HTML for SEO: meta tags (title, description, canonical, og:*, twitter:*) from restaurant data, semantic sections, and a Vue mount point; template choice selects the layout. No internal `id` in any response or view.
- **2026-02-20**: **Logo/banner upload: 422 for image processing errors.** Upload logo and upload banner sections now document that 422 is also returned for image processing errors (invalid/corrupt image, resize failure), with message and errors.file indicating the reason.
- **2026-02-20**: **Logo and banner image optimization.** On upload, logo images are resized to fit within 300×300 px and banner images within 1920×600 px (proportional; no cropping). Images already within those dimensions are stored as-is. Resizing is done server-side with PHP GD before storage; the stored file is the (possibly resized) image. No change to API response shape; no internal `id` in any response.
- **2026-02-20**: **Availability for categories and menu items.** Categories and menu_items tables: added **availability** (JSON, nullable). Same shape as restaurant operating_hours (keyed by day sunday–saturday, each day `{ "open": bool, "slots": [ { "from": "HH:MM", "to": "HH:MM" }, ... ] }`). When **null** = "all available" (default). Validation via existing OperatingHoursRule and OperatingHoursSlotValidator (no overlapping slots per day). Category API: list/show/create/update include and accept optional **availability** (PATCH null to clear). Menu item API (restaurant): list/show/create/update include and accept optional **availability** (null to clear). GET `/api/public/restaurants/{slug}`: each menu_item in response includes **availability** (null = always available). No internal `id` in any response.
- **2026-02-20**: **Owner feedback (feature requests).** New entity: owner feedback (uuid, user_id, restaurant_id nullable, title nullable, message, status default pending). Owner (Bearer + verified): POST `/api/owner-feedback` (message required, title and restaurant optional; 403 if restaurant uuid sent but not owned), GET `/api/owner-feedback` (list own submissions, newest first). Superadmin: GET `/api/superadmin/owner-feedbacks` (list all with submitter and restaurant), PATCH `/api/superadmin/owner-feedbacks/{uuid}` (optional status: pending | reviewed). No internal `id` in any response.
- **2026-02-20**: **Superadmin module.** Superadmin identity via `is_superadmin` on users table; seeded from `SUPERADMIN_EMAIL` and `SUPERADMIN_PASSWORD` (env). User payload: added **is_superadmin**, **is_active** (GET /api/user, login). **is_active** (boolean, default true) added to users; deactivated users cannot log in (403 "Your account has been deactivated."). Superadmin-only endpoints (Bearer + verified + superadmin; 403 if not): GET `/api/superadmin/stats` (restaurants_count, users_count, paid_users_count), GET `/api/superadmin/users` (list all users), PATCH `/api/superadmin/users/{uuid}` (optional is_paid, is_active; cannot change own is_active), GET `/api/superadmin/restaurants` (read-only list). No internal `id` in any response.
- **2026-02-20**: **Feedbacks.** New entity: feedback per restaurant (uuid, rating 1-5, text, name, is_approved). Public: POST `/api/public/restaurants/{slug}/feedback` (no auth, rate-limited 10/min). Owner: GET `/api/restaurants/{restaurant}/feedbacks`, PATCH `/api/restaurants/{restaurant}/feedbacks/{feedback}` (is_approved), DELETE. GET `/api/public/restaurants/{slug}` response includes **feedbacks** array (approved only: uuid, rating, text, name, created_at). No internal `id` in any response.
- **2026-02-20**: **Menu item tags: default only.** Custom tag creation/update/delete removed. GET `/api/menu-item-tags` now returns **default tags only** (user_id null). POST `/api/menu-item-tags`, PATCH/PUT `/api/menu-item-tags/{tag}`, and DELETE `/api/menu-item-tags/{tag}` always return **403** with message: "Custom menu item tags are not available. Use the default tags." Menu items still have many-to-many with tags; owner and public payloads include tags; PATCH menu item still accepts **tag_uuids** (only default tag UUIDs allowed). User payload **is_paid** retained for future paid features (e.g. multiple restaurants).
- **2026-02-20**: **Menu item tags:** Tag payload includes **is_default** (boolean): true for system tags (read-only in UI), false for user's custom tags.
- **2026-02-20**: **Menu item tags.** New entity: menu item tag (uuid, color, icon, text). **Default (system) tags** (user_id null) seeded for all users; many-to-many with menu items via pivot `menu_item_menu_item_tag`. Restaurant menu item payload (list/show/create/update) and public GET `/api/public/restaurants/{slug}` menu_items include **tags**: `[{ uuid, color, icon, text }]`. POST/PATCH restaurant menu items accept optional **tag_uuids** (array); validated against default tags only. No internal `id` in any tag or menu item response.
- **2026-02-20**: **Restaurant menu items: "Not Available" (is_available).** Column **is_available** (boolean, default true) added to menu_items. List, show, create, and update responses include **is_available**. PATCH `/api/restaurants/{restaurant}/menu-items/{item}` accepts optional **is_available** (boolean). When false, the item remains on the public menu but the frontend should show a "Not Available" indicator (unlike is_active which hides the item). GET `/api/public/restaurants/{slug}` includes **is_available** for each menu_item. Create defaults is_available to true. No internal `id` in responses.
- **2026-02-20**: **Restaurant menu items: per-item visibility (is_active).** Column **is_active** (boolean, default true) added to menu_items. List, show, create, and update responses include **is_active**. PATCH `/api/restaurants/{restaurant}/menu-items/{item}` accepts optional **is_active** (boolean) to show or hide the item on the public menu. GET `/api/public/restaurants/{slug}` excludes menu items where is_active is false (in addition to filtering by category is_active). No internal `id` in responses.
- **2026-02-20**: **Restaurant menu items: add ending variants only for catalog items with variants.** POST `/api/restaurants/{restaurant}/menu-items` accepts optional **source_variant_uuid** when **source_menu_item_uuid** is set. If the catalog item has type `with_variants`, **source_variant_uuid** is **required** and must be the uuid of one of that item’s variant_skus; only each end variant is addable, not the base item. If the catalog item is simple or combo, **source_variant_uuid** must be null/absent (422 otherwise). Restaurant menu item payload (list/show/create/update) includes **source_variant_uuid** when the item was added with a variant; effective name and price are derived from that variant (base name + variant option_values label; price from variant). No internal `id` in responses.
- **2026-02-19**: **Catalog menu items: combos and variants.** User-level menu items support **type**: `simple` (default), `combo`, or `with_variants`. **Combo:** optional `combo_price`; `combo_entries` array (menu_item_uuid, variant_uuid when referenced item has variants, quantity, modifier_label). Referenced items must be owned by the user. **With variants:** `variant_option_groups` (name + ordered values) and `variant_skus` (uuid, option_values, price, image_url optional); variant_skus must cover exactly the Cartesian product. GET/POST/PATCH `/api/menu-items` and GET `/api/menu-items/{item}` payloads include type and, when applicable, combo_entries or variant_option_groups + variant_skus. No internal `id` in any response; variant_skus use public `uuid`. Restaurant context “add ending variant only” and variant image upload are follow-ups.
- **2026-02-19**: Public restaurant by slug: response now includes **operating_hours** (same shape as owner restaurant payload; null when not set) for display of opening hours on public pages.
- **2026-02-19**: User-level menu items: GET `/api/menu-items` now returns **only standalone (catalog) items**. Restaurant menu items are no longer included; use GET `/api/restaurants/{restaurant}/menu-items` for per-restaurant lists. Fixes duplicate items on the Menu items (catalog) page after adding a catalog item to a restaurant category.
- **2026-02-19**: Restaurants: **operating_hours** (optional). Column added to restaurants table (JSON, nullable). Create/update accept `operating_hours`: object keyed by day (sunday–saturday), each day `{ "open": bool, "slots": [ { "from": "HH:MM", "to": "HH:MM" }, ... ] }`. Times 24h (HH:MM or HH:MM:SS). Per day, timeslots must not overlap; `from` must be before `to`. List/show/update and create response include `operating_hours` in payload. Same structure will be reused for menu item availability.
- **2026-02-19**: Restaurants: **currency** (ISO 4217 code, default USD). Column added to restaurants table. Update accepts `currency`; list/show/update and public GET `/api/public/restaurants/{slug}` include `currency` in payload. Frontend uses it for all price display in restaurant context.
- **2026-02-19**: **Remove language:** Removing a language from a restaurant only removes it from restaurant_languages. Restaurant, menu, and category translation rows are no longer deleted when a language is removed; they persist until the entity is deleted (cascade).
- **2026-02-19**: **Menus:** Translatable name and description. New `menu_translations` table (menu_id, locale, name, description). Menu payload includes `translations` (locale → { name, description }) and `name` (resolved from default locale). Create/update accept `translations`; locales must be installed. Backward compat: `name` alone still supported and maps to default locale.
- **2026-02-19**: **Categories:** Category translations include optional `description` per locale. Create/update accept `description` in each locale; payload extends to `Record<locale, { name, description? }>`.
- **2026-02-18**: Restaurants: **primary_color** (optional hex, e.g. #ff5500) for public site theming. Create/update accept primary_color; payload and public GET include primary_color.
- **2026-02-19**: Restaurants: API reference aligned with implementation. Restaurant payload includes `tagline`, `public_url`, `default_locale`, `languages`. Create body documents `tagline`, `default_locale`. Update: slug cannot be changed after create (not accepted on PATCH/PUT). **Public restaurant by slug:** GET `/api/public/restaurants/{slug}` (no auth) documented; response includes name, tagline, slug, logo_url, banner_url, default_locale, languages, locale, description, menu_items (uuid, name, description, price, sort_order only; menu items filtered by active categories; no internal `id`).
- **2026-02-19**: Categories: **is_active** (boolean, default true). Update category accepts `is_active`; when false, category and its items are excluded from the public restaurant menu. Delete category: existing endpoint; menu items in the category have their `category_id` set to null (nullOnDelete).
- **2026-02-19**: Menu items: base **price** on catalog (user-level) and optional **overrides** in restaurant context. User-level payload and create/update include `price`. Restaurant create can use `source_menu_item_uuid` to add a catalog item to a category; optional `price_override` and `translation_overrides`. Restaurant update accepts `price_override`, `translation_overrides`, and **revert_to_base** (boolean) to clear overrides. Payload includes effective `price` and `translations`; when item is from catalog, also `source_menu_item_uuid`, `price_override`, `translation_overrides`, `base_price`, `base_translations`, `has_overrides`. Public restaurant menu items include `price` (effective).
- **2026-02-18**: Menus: clarified that a restaurant can have multiple menus and that POST create can be called repeatedly.
- **2026-02-18**: User-level menu items: GET/POST `/api/menu-items`, GET/PATCH/DELETE `/api/menu-items/{item}` for standalone list/create and for editing any owned item (standalone or restaurant). Payload may include `restaurant_uuid` (null for standalone).
- **2026-02-18**: Menus: CRUD + reorder (GET/POST/PATCH/DELETE `/api/restaurants/{restaurant}/menus`, POST reorder). Categories: CRUD + reorder under menus; translations per locale (GET/POST/PATCH/DELETE `/api/restaurants/{restaurant}/menus/{menu}/categories`, POST reorder). Menu items: optional `category_uuid`, payload includes `category_uuid`; POST `/api/restaurants/{restaurant}/categories/{category}/menu-items/reorder` to reorder items within a category. All by `uuid`; no internal `id` in responses.
- **2026-02-18**: **Machine translation (translate service):** Document 502 response for translation service errors and configuration (TRANSLATION_DRIVER=libre, LIBRE_TRANSLATE_URL, optional LIBRE_TRANSLATE_API_KEY). No API change.
- **2026-02-16**: Security: logo/banner uploads use server-derived file extension from MIME type (not client filename). POST `/api/translate` rate-limited (30/min per user). API reference: upload storage note, Machine translation section with rate limit.
- **2026-02-14**: Restaurants: free-tier limit (one restaurant per user; 403 when exceeded). Update slug validated for uniqueness (422 if taken). Docs: free-tier error, public logo/banner with Content-Type, slug uniqueness, upload validation (2MB, image types, custom message).
- **2026-02-14**: Restaurants API: CRUD (list, show, create, update, delete) and media (upload logo/banner, public serve URLs). All by `uuid`; no internal `id` in responses. Endpoints: GET/POST `/api/restaurants`, GET/PUT/PATCH/DELETE `/api/restaurants/{uuid}`, POST `/api/restaurants/{uuid}/logo`, POST `/api/restaurants/{uuid}/banner`, GET `/api/restaurants/{uuid}/logo`, GET `/api/restaurants/{uuid}/banner`.
- **2025-02-15**: User payload and verification URLs use **uuid** (public identifier); internal numeric **id** is no longer exposed in any API response. Routes: GET `/api/email/verify/{uuid}/{hash}`, GET `/api/email/verify-new/{uuid}/{hash}`.
- **2025-02-14**: Profile API: PATCH `/api/user` (update name and/or email; email change requires verification at new address), POST `/api/profile/password` (change password with current password). GET `/api/email/verify-new/{uuid}/{hash}` for new-email verification (signed). User payload may include `pending_email` in profile responses.
- **2025-02-13**: Verification email link now points to frontend `/email/verify` (frontend then calls API). Reset link already points to frontend `/reset-password`.
- **2025-02-13**: Login when email not verified: return **403** with `{ "message": "Your email address is not verified." }`; keep **422** for wrong credentials/validation only. API reference Login section and errors clarified.
- **2025-02-13**: Initial document – health, register (no token, verify email), login (block unverified), forgot/reset password, email verify/resend, social login (Google/Facebook/Instagram), user, logout, logout-all.
