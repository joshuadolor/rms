# Frontend: Service-layer caching

We use a **cache-on-read, invalidate-on-write** pattern for selected API data in the frontend services. If the same request is made again and a cache entry exists, we **do not call the API** — we return the cached data. When data is mutated (create/update/delete), we clear the relevant cache so the next read fetches fresh data.

---

## Cache key rule

**The cache key must identify the API call that was made.**  
Store a key that encodes whatever defines that request (resource, path params, query params). Examples:

- List restaurants with pagination → key includes `page` and `per_page` (e.g. `restaurants:list:1:15`).
- List menu items for a restaurant → key is the restaurant UUID (e.g. one entry per restaurant).
- User menu items list (GET /api/menu-items) → single key `user-menu-items:list`.

Same key on a later call → cache hit, no API request. Different key or no key → fetch from API, then store under that key.

---

## Behavior

1. **On fetch (read):** The service builds a cache key from the request (resource + params). It checks the in-memory cache for that key. If an entry exists, it returns a **copy** of the cached data and does **not** call the API. If not, it calls the API, stores the response under that key, and returns it.
2. **On mutate (write):** After a successful create/update/delete (and reorder when applicable), the service **invalidates** the cache entries that are affected. The next read will then miss the cache and fetch from the API.

---

## Where it’s applied

### Restaurants list (paginated)

- **Cached:** `listRestaurants(params)` / `restaurantService.list({ page, per_page })` — list of restaurants.
- **Cache key:** `restaurants:list:{page}:{per_page}` — one entry per (page, per_page).
- **Invalidation:** All restaurants list entries are cleared when: `createRestaurant`, `updateRestaurant`, `deleteRestaurant`.

### Menu items (per restaurant)

- **Cached:** `listMenuItems(restaurantUuid)` — list of menu items for a restaurant.
- **Cache key:** Restaurant UUID (one cache entry per restaurant).
- **Invalidation:** Cache for that restaurant is cleared when:
  - **Create:** `createMenuItem(uuid, payload)` — new item added.
  - **Update:** `updateMenuItem(uuid, itemUuid, payload)` — item changed.
  - **Delete:** `deleteMenuItem(uuid, itemUuid)` — item removed.
  - **Reorder:** `reorderMenuItems(restaurantUuid, categoryUuid, order)` — order changed.

After any of these, the next `listMenuItems(uuid)` for that restaurant will hit the API and repopulate the cache.

### User menu items list (GET /api/menu-items)

- **Cached:** `menuItemService.list()` / `listUserMenuItems()` — all menu items the user can access (used on the /app/menu-items page).
- **Cache key:** `user-menu-items:list` (single entry).
- **Invalidation:** Cleared when:
  - **Standalone:** `menuItemService.create`, `update`, `delete`.
  - **Restaurant flow:** `restaurantService.createMenuItem`, `updateMenuItem`, `deleteMenuItem`, `reorderMenuItems` (restaurant.service calls `invalidateUserMenuItemsListCache()` so the app menu-items list stays in sync).

After any of these, the next `menuItemService.list()` will hit the API and repopulate the cache.

### Categories (per menu)

- **Cached:** `listCategories(restaurantUuid, menuUuid)` — list of categories for a menu.
- **Cache key:** `categories:{restaurantUuid}:{menuUuid}` — one entry per (restaurant, menu).
- **Invalidation:** Cache for that menu is cleared when:
  - **Create:** `createCategory(restaurantUuid, menuUuid, payload)`
  - **Update:** `updateCategory(restaurantUuid, menuUuid, categoryUuid, payload)`
  - **Delete:** `deleteCategory(restaurantUuid, menuUuid, categoryUuid)`
  - **Reorder:** `reorderCategories(restaurantUuid, menuUuid, order)`

After any of these, the next `listCategories(restaurantUuid, menuUuid)` for that menu will hit the API and repopulate the cache.

---

## Implementation details

- **Storage:** In-memory only (e.g. a `Map` in the service module). Cache is lost on full page reload or when the app is closed.
- **No TTL:** Invalidation is purely mutation-based; we don't expire entries by time.
- **Return value:** Cached data is returned in the same shape as the API response. Callers do not need to change; they get either a cached or a fresh response.
- **Safety:** When returning from cache, the service returns a **copy** of the stored data (e.g. cloned arrays/objects) so that caller mutations do not corrupt the cache.

---

## Extending to other resources

1. **Choose a cache key** that uniquely identifies the API call (resource + path/query params).
2. **On read:** In the list/get function, check the cache by key; on hit return a copy of the cached value; on miss call the API, store the result in the cache, then return it.
3. **On write:** In every create/update/delete (and reorder if applicable) that affects that resource, clear the cache for the affected key(s) after a successful API call.

Document the cached function and invalidation points in this file and in the service’s JSDoc.
