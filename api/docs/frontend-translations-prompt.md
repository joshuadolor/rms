# Frontend prompt: Translations & menu items

Use this as the prompt for the frontend (Vue/Playwright) work. The backend APIs below are implemented and ready.

---

## 1. Context

- **LibreTranslate** will be integrated later; the backend is ready (stub returns original text until `LIBRE_TRANSLATE_URL` is set). Use the translate API when you want to offer “Translate with AI” in the UI.
- **Business rules**: Feature availability (e.g. “free = one restaurant”) is enforced in the backend. The API returns `403` with a message when the user cannot create another restaurant (or other restricted action). No separate “plan” or “feature” endpoint is required for the first version.
- **Languages per restaurant**: Each restaurant has a list of **installed** locales (e.g. `en`, `nl`, `ru`). Users can add/remove languages per restaurant (unlimited). The **default locale** is used for the public site when no language is selected. Translatable content is stored per locale.

---

## 2. What is translatable

| Entity            | Translatable fields              |
|-------------------|----------------------------------|
| Restaurant        | **Description** (per locale)     |
| Menu item         | **Name**, **Description** (per locale) |

Restaurant **name**, **address**, etc. are not translatable in the first version; only **description** is. Menu items have no single “name” or “description” at the root; they only exist inside **translations** per locale.

---

## 3. Backend APIs (base path: `/api`)

All routes under “Auth required” need `Authorization: Bearer <token>` and a verified email.

### 3.1 Public

- **GET /locales**  
  Returns the list of **supported** locale codes (e.g. `en`, `nl`, `ru`) that can be installed per restaurant.  
  Response: `{ "data": ["en", "nl", "ru", "de", "fr", "es", "it", "pt"] }`

### 3.2 Auth required

**Restaurant payload (existing)**  
`GET /restaurants`, `GET /restaurants/:uuid`, `POST /restaurants`, `PATCH /restaurants/:uuid` now also return:

- `default_locale` (string, e.g. `"en"`)
- `languages` (array of installed locale codes, e.g. `["en", "nl"]`)

**Restaurant languages (install / uninstall)**

- **GET /restaurants/:restaurant/languages**  
  List installed locales for this restaurant.  
  Response: `{ "data": ["en", "nl"] }`

- **POST /restaurants/:restaurant/languages**  
  Add a language.  
  Body: `{ "locale": "nl" }`  
  Response: `201` and `{ "message": "...", "data": ["en", "nl"] }`  
  Validation: `locale` must be one of the supported locales and not already installed.

- **DELETE /restaurants/:restaurant/languages/:locale**  
  Remove a language. Fails if `locale` is the restaurant’s `default_locale` (change default first).  
  Response: `204` on success.

**Restaurant translations (description per locale)**

- **GET /restaurants/:restaurant/translations**  
  All description translations.  
  Response: `{ "data": { "en": { "description": "..." }, "nl": { "description": "..." } } }`

- **GET /restaurants/:restaurant/translations/:locale**  
  Single locale.  
  Response: `{ "data": { "description": "..." } }` or `{ "description": null }` if not set.

- **PUT or PATCH /restaurants/:restaurant/translations/:locale**  
  Create or update description for that locale.  
  Body: `{ "description": "Optional long text or null" }`  
  Response: `200` and `{ "message": "...", "data": { "description": "..." } }`  
  Validation: `locale` must be one of the restaurant’s **installed** languages.

**Menu items (CRUD + translations)**

- **GET /restaurants/:restaurant/menu-items**  
  List menu items (ordered by `sort_order`).  
  Response: `{ "data": [ { "uuid": "...", "sort_order": 0, "translations": { "en": { "name": "Pizza", "description": "..." }, "nl": { "name": "Pizza", "description": "..." } }, "created_at": "...", "updated_at": "..." } ] }`

- **POST /restaurants/:restaurant/menu-items**  
  Create a menu item.  
  Body: `{ "sort_order": 0, "translations": { "en": { "name": "Pizza", "description": "..." }, "nl": { "name": "Pizza", "description": null } } }`  
  Only locales that are **installed** for the restaurant are stored.  
  Response: `201` and full menu item object.

- **GET /restaurants/:restaurant/menu-items/:item**  
  Single menu item (same shape as one element in the list).

- **PUT or PATCH /restaurants/:restaurant/menu-items/:item**  
  Update sort order and/or translations.  
  Body: `{ "sort_order": 1, "translations": { "nl": { "name": "Pizza", "description": "..." } } }`  
  Only provided locales are updated; others are left as-is.

- **DELETE /restaurants/:restaurant/menu-items/:item**  
  Delete menu item. Response: `204`.

**Machine translation (for “Translate” button)**

- **POST /translate**  
  Body: `{ "text": "Hello world", "from_locale": "en", "to_locale": "nl" }`  
  Response: `200` and `{ "translated_text": "Hallo wereld" }`  
  If LibreTranslate is not configured: `503` and a message. Use this to pre-fill a translation, then save via the translation endpoints above.

---

## 4. Frontend tasks (prompt for implementation)

1. **Restaurant settings – Languages**
   - Show installed languages and “Add language” (dropdown of supported locales from `GET /locales`, excluding already installed).
   - Allow removing a language (except the default); confirm before delete.
   - Allow setting the **default locale** (e.g. dropdown or radio). Persist via `PATCH /restaurants/:uuid` with `{ "default_locale": "nl" }`.

2. **Restaurant description (per locale)**
   - For each installed language, show a field (or tab) for “Description”. Load/save via `GET/PUT /restaurants/:restaurant/translations/:locale` with body `{ "description": "..." }`.
   - Optionally add a “Translate” button that calls `POST /translate` and pre-fills the description for the target locale (then user can edit and save).

3. **Menu items**
   - List menu items for a restaurant (`GET /restaurants/:restaurant/menu-items`). Allow reordering (update `sort_order` with `PATCH /restaurants/:restaurant/menu-items/:item`).
   - Create/edit menu item: form with one block per **installed** locale (name + description). Create with `POST .../menu-items` and `translations: { "en": { "name": "...", "description": "..." }, ... }`; update with `PATCH .../menu-items/:item` and same structure.
   - Optional: “Translate” for a given locale that calls `POST /translate` and fills name/description for that locale, then user saves.

4. **Generic / app site translations**
   - The backend does not yet serve app-wide translation strings (e.g. “Login”, “Sign up”). Use the frontend i18n solution (e.g. vue-i18n) with the same locale codes (`en`, `nl`, `ru`, …). Load app strings from JSON or a CMS later; for now, hardcode or use a static JSON per locale. The **default locale** of the restaurant can be used to choose the app language when the user is on a restaurant’s public page.

5. **Errors**
   - On `403`, show the API `message` (e.g. “Free tier allows one restaurant. Upgrade to add more.”).
   - On `422`, show validation `errors` (e.g. `locale`, `default_locale`).

---

## 5. Summary

- **Locales**: `GET /locales` for supported list. Per-restaurant installed list and default are on the restaurant payload and `GET /restaurants/:id/languages`.
- **Restaurant description**: `GET/PUT /restaurants/:id/translations/:locale` with `{ "description": "..." }`.
- **Menu items**: Full CRUD under ` /restaurants/:id/menu-items` with `translations` keyed by locale (`name`, `description`).
- **Translate**: `POST /translate` with `text`, `from_locale`, `to_locale` to get `translated_text`; then save via the relevant translation or menu-item API.

Use this document as the single reference for the frontend implementation of translations and menu items.
