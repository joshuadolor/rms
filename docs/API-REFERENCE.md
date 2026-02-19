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
  "user": { "uuid", "name", "email", "email_verified_at", "pending_email" }
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
  languages: string[];      // installed locales
  logo_url: string | null;   // e.g. https://api.example.com/api/restaurants/{uuid}/logo
  banner_url: string | null; // e.g. https://api.example.com/api/restaurants/{uuid}/banner
  created_at: string;       // ISO 8601
  updated_at: string;       // ISO 8601
}
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
  "default_locale": "string (optional, e.g. en, nl, ru; must be in supported locales)"
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

**Body (JSON):** Same fields as create (except **slug**); all optional. Send only fields to change. **Slug cannot be changed** after create (subdomain stability); the API does not accept `slug` on update. **primary_color** may be set to a hex value (e.g. `#ff5500`) or `null` to clear.

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

Returns public restaurant data and menu items for subdomain or `/r/:slug` pages. **No authentication.** Menu items are included only if uncategorized or in a category with `is_active` true. **No internal `id` in any field.**

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
    "languages": ["en", "nl", ...],
    "locale": "string",
    "description": "string | null",
    "menu_items": [
      {
        "uuid": "string",
        "name": "string",
        "description": "string | null",
        "price": "number | null",
        "sort_order": "number"
      }
    ]
  }
}
```

**404:** Restaurant not found for the given slug.

---

## Menus

Each restaurant can have **multiple** menus (e.g. "Lunch", "Dinner", "Drinks"). **Active** menus are shown on the public website. You can call `POST /api/restaurants/{restaurant}/menus` repeatedly to create additional menus. All endpoints use **Bearer + verified** and require restaurant ownership. Path parameters use `uuid` (never internal `id`).

### Menu payload (common)

```ts
{
  uuid: string;
  name: string | null;
  is_active: boolean;
  sort_order: number;
  created_at: string;  // ISO 8601
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
  "name": "string (optional, max 255)",
  "is_active": "boolean (optional, default true)",
  "sort_order": "integer (optional, min 0)"
}
```

**Response (201):** `{ "message": "Menu created.", "data": { menu payload } }`. **403:** Not owner. **404:** Restaurant not found. **422:** Validation.

### Update menu

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/restaurants/{restaurant}/menus/{menu}` | Bearer + verified |

**Body:** Same fields as create; all optional. **Response (200):** `{ "message": "Menu updated.", "data": { menu payload } }`. **403/404/422:** As above.

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
  translations: Record<string, { name: string }>;  // locale -> { name }
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
    "locale": { "name": "string (required with translations, max 255)" }
  }
}
```

Translations must use locales installed for the restaurant. **Response (201):** `{ "message": "Category created.", "data": { category payload } }`. **422:** Uninstalled locale(s).

### Update category

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/restaurants/{restaurant}/menus/{menu}/categories/{category}` | Bearer + verified |

**Body:** sort_order, is_active (optional boolean), translations (optional). **Response (200):** `{ "message": "Category updated.", "data": { category payload } }`.

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

## Menu items (user-level)

List, create, and manage menu items from the standalone “Menu items” page. Items can be **standalone** (not tied to any restaurant) or belong to a restaurant the user owns. All endpoints **Bearer + verified**.

### User-level menu item payload

Same as menu item payload below, plus optional **restaurant_uuid** (present when the item belongs to a restaurant; `null` for standalone).

### List all my menu items

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/menu-items` | Bearer + verified |

**Response (200):** `{ "data": [ { user-level menu item payload } ] }` (standalone first, then by category/sort_order).

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
  "price": "number (optional, min 0)",
  "translations": {
    "locale": { "name": "string (required)", "description": "string | null (optional)" }
  }
}
```

At least one translation with a non-empty **name** is required. **Response (201):** `{ "message": "Menu item created.", "data": { user-level menu item payload } }`. **422:** No name in any translation.

### Update menu item

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/menu-items/{item}` | Bearer + verified |

**Body:** sort_order, price (optional), translations (optional). **Response (200):** `{ "message": "Menu item updated.", "data": { user-level menu item payload } }`. **404:** Not found or not owned.

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
  price: number | null;   // effective price (base or override)
  translations: Record<string, { name: string; description: string | null }>;  // effective per locale
  created_at: string;
  updated_at: string;
}
```

When the item is a **restaurant usage of a catalog item** (added from “Menu items” into a restaurant category), the payload also includes:

```ts
{
  source_menu_item_uuid: string;
  price_override: number | null;      // null = use base price
  translation_overrides: Record<string, { name?: string; description?: string | null }>;
  base_price: number | null;          // catalog item base price
  base_translations: Record<string, { name: string; description: string | null }>;
  has_overrides: boolean;             // true if any override is set (for “Revert to base” UI)
}
```

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

- **Add from catalog:** `source_menu_item_uuid` (required), `category_uuid`, `sort_order`, optional `price_override`, `translation_overrides` (per-locale name/description). Catalog item must be a standalone menu item owned by the user. No `translations` needed; effective values come from base + overrides.
- **Create new item:** `category_uuid`, `sort_order`, optional `price`, and `translations` (required; locales must be installed for the restaurant).

```json
{
  "category_uuid": "string (optional)",
  "sort_order": "integer (optional, min 0)",
  "source_menu_item_uuid": "string (optional, uuid of catalog item; when set, adds catalog item to category)",
  "price_override": "number (optional, min 0; only when source_menu_item_uuid set)",
  "translation_overrides": { "locale": { "name": "string", "description": "string | null" } },
  "price": "number (optional, min 0; only when creating new item)",
  "translations": {
    "locale": { "name": "string (required with translations)", "description": "string | null (optional)" }
  }
}
```

**Response (201):** `{ "message": "Menu item created.", "data": { menu item payload } }`. **422:** Uninstalled locale(s). **403:** Catalog item not found or not owned.

### Update menu item

| Method | Path | Auth |
|--------|------|------|
| PUT/PATCH | `/api/restaurants/{restaurant}/menu-items/{item}` | Bearer + verified |

**Body:** category_uuid, sort_order, translations (for items without source), price (for items without source), price_override, translation_overrides (for items with source), **revert_to_base** (boolean; when true, clears price_override and translation_overrides so effective values revert to catalog base). **Response (200):** `{ "message": "Menu item updated.", "data": { menu item payload } }`.

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
