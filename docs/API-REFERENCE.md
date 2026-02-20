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
  is_paid?: boolean;                 // reserved for future paid features (e.g. multiple restaurants)
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

**Response (200):** Only if email is verified.
```json
{
  "message": "Logged in successfully.",
  "user": { "uuid", "name", "email", "email_verified_at" },
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
  "user": { "uuid", "name", "email", "email_verified_at", "pending_email", "is_paid" }
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
  "operating_hours": "object (optional; see Restaurant payload operating_hours shape; timeslots must not overlap per day)"
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

**Body (JSON):** Same fields as create (except **slug**); all optional. Send only fields to change. **Slug cannot be changed** after create (subdomain stability); the API does not accept `slug` on update. **primary_color** may be set to a hex value (e.g. `#ff5500`) or `null` to clear. **operating_hours**: optional; when present, same shape as in the restaurant payload; timeslots must not overlap per day; send `null` to clear.

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
**Storage:** The stored filename is server-derived from the file’s MIME type (e.g. `logo.jpg`, `logo.png`). The client-provided filename/extension is not used.

**Response (200):**
```json
{
  "message": "Logo updated.",
  "data": { restaurant payload }
}
```

**403:** Not owner. **404:** Restaurant not found. **422:** Validation (e.g. file type/size).

---

### Upload banner

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{uuid}/banner` | Bearer + verified |

**Body:** Same as logo – `multipart/form-data`, field **`file`** (image: jpeg, png, gif, webp; max 2MB). Same validation and error messages. Stored filename is server-derived from MIME (e.g. `banner.jpg`), not from the client filename.

**Response (200):** `{ "message": "Banner updated.", "data": { restaurant payload } }`  
**403/404/422:** Same as logo.

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

### Public restaurant by slug (no auth)

Returns public restaurant data and menu items for subdomain or `/r/:slug` pages. **No authentication.** Menu items are included only if **is_active** is true and (uncategorized or in a category with `is_active` true). Hidden items (is_active false) do not appear. Items with **is_available** false are still included; the frontend should show a "Not Available" indicator for them. **No internal `id` in any field.**

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/public/restaurants/{slug}` | No |

**Query:** `locale` (optional). If provided and installed for the restaurant, description and menu item names/descriptions use that locale; otherwise the restaurant’s default locale is used.

**Response (200):**
```json
{
  "data": {
    "name": "string",
    "tagline": "string | null",
    "primary_color": "string | null",
    "slug": "string",
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
        "name": "string",
        "description": "string | null",
        "price": "number | null",
        "sort_order": "number",
        "is_available": "boolean",
        "tags": [{ "uuid": "string", "color": "string", "icon": "string", "text": "string" }, ...]
      }
    ],
    "feedbacks": [
      { "uuid": "string", "rating": "number (1-5)", "text": "string", "name": "string", "created_at": "string (ISO 8601)" }
    ]
  }
}
```

`operating_hours` has the same shape as in the restaurant payload (see **Operating hours shape** above); `null` when not set. **feedbacks** contains only **approved** feedbacks (owner approves via PATCH); no internal `id` in any field.

**404:** Restaurant not found for the given slug.

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
  "translations": {
    "locale": { "name": "string (required with translations, max 255)", "description": "string (optional, nullable)" }
  }
}
```

Translations must use locales installed for the restaurant. **Response (201):** `{ "message": "Category created.", "data": { category payload } }`. **422:** Uninstalled locale(s).

### Update category

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}` | Bearer + verified |

**Body:** sort_order, is_active (optional boolean), translations (optional; each locale: name, description optional). **Response (200):** `{ "message": "Category updated.", "data": { category payload } }`.

### Delete category

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}` | Bearer + verified |

**Response (204):** No content.

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
  price: number | null;   // effective price (base or override)
  translations: Record<string, { name: string; description: string | null }>;  // effective per locale
  tags: Array<{ uuid: string; color: string; icon: string; text: string }>;  // menu item tags attached to this item
  created_at: string;
  updated_at: string;
}
```

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

**Response (201):** `{ "message": "Menu item created.", "data": { menu item payload } }`. **422:** Uninstalled locale(s), source_variant_uuid validation, or invalid tag_uuids. **403:** Catalog item not found or not owned.

### Update menu item

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/restaurants/{restaurant}/menu-items/{item}` | Bearer + verified |

**Body:** category_uuid, sort_order, **is_active** (optional boolean; when false, item is hidden on the public menu), **is_available** (optional boolean; when false, item is shown on the public menu but marked "Not Available"), translations (for items without source), price (for items without source), price_override, translation_overrides (for items with source), **revert_to_base** (boolean; when true, clears price_override and translation_overrides so effective values revert to catalog base), **tag_uuids** (optional array of UUIDs; replaces item's tags; only default tag UUIDs allowed; 422 if invalid). **Response (200):** `{ "message": "Menu item updated.", "data": { menu item payload } }`.

### Delete menu item

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/restaurants/{restaurant}/menu-items/{item}` | Bearer + verified |

**Response (204):** No content.

### Reorder menu items (within category)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/restaurants/{restaurant}/categories/{category}/menu-items/reorder` | Bearer + verified |

**Body (JSON):** `{ "order": ["itemUuid1", "itemUuid2", ...] }`. **Response (200):** `{ "message": "Order updated." }`. **404:** Restaurant or category not found.

---

## Machine translation

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

**Response (200):**
```json
{
  "translated_text": "string"
}
```

**429:** Too many requests; wait before retrying.  
**503:** Translation service not configured or unavailable.  
**422:** Validation (e.g. missing or invalid fields).

---

## Changelog

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
- **2026-02-16**: Security: logo/banner uploads use server-derived file extension from MIME type (not client filename). POST `/api/translate` rate-limited (30/min per user). API reference: upload storage note, Machine translation section with rate limit.
- **2026-02-14**: Restaurants: free-tier limit (one restaurant per user; 403 when exceeded). Update slug validated for uniqueness (422 if taken). Docs: free-tier error, public logo/banner with Content-Type, slug uniqueness, upload validation (2MB, image types, custom message).
- **2026-02-14**: Restaurants API: CRUD (list, show, create, update, delete) and media (upload logo/banner, public serve URLs). All by `uuid`; no internal `id` in responses. Endpoints: GET/POST `/api/restaurants`, GET/PUT/PATCH/DELETE `/api/restaurants/{uuid}`, POST `/api/restaurants/{uuid}/logo`, POST `/api/restaurants/{uuid}/banner`, GET `/api/restaurants/{uuid}/logo`, GET `/api/restaurants/{uuid}/banner`.
- **2025-02-15**: User payload and verification URLs use **uuid** (public identifier); internal numeric **id** is no longer exposed in any API response. Routes: GET `/api/email/verify/{uuid}/{hash}`, GET `/api/email/verify-new/{uuid}/{hash}`.
- **2025-02-14**: Profile API: PATCH `/api/user` (update name and/or email; email change requires verification at new address), POST `/api/profile/password` (change password with current password). GET `/api/email/verify-new/{uuid}/{hash}` for new-email verification (signed). User payload may include `pending_email` in profile responses.
- **2025-02-13**: Verification email link now points to frontend `/email/verify` (frontend then calls API). Reset link already points to frontend `/reset-password`.
- **2025-02-13**: Login when email not verified: return **403** with `{ "message": "Your email address is not verified." }`; keep **422** for wrong credentials/validation only. API reference Login section and errors clarified.
- **2025-02-13**: Initial document – health, register (no token, verify email), login (block unverified), forgot/reset password, email verify/resend, social login (Google/Facebook/Instagram), user, logout, logout-all.
