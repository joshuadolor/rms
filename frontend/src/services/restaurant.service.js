/**
 * Restaurant API service. All endpoints require auth (Bearer token).
 * Uses uuid in paths. Logo/banner URLs are returned by the API (serve at GET /api/restaurants/{uuid}/logo|banner).
 * List endpoints use in-memory cache; see docs/FRONTEND-SERVICE-CACHE.md.
 */

import api from './api'
import { invalidateUserMenuItemsListCache } from './menuItem.service.js'

// --- Restaurants list cache (key = request identity: resource + params) ---

/** In-memory cache: key "restaurants:list:{page}:{per_page}" -> response. Cleared on create/update/delete restaurant. */
const restaurantsListCache = new Map()

function invalidateRestaurantsListCache() {
  for (const key of restaurantsListCache.keys()) {
    if (key.startsWith('restaurants:list:')) restaurantsListCache.delete(key)
  }
}

/**
 * List restaurants (paginated). Uses cache; same page+per_page returns cached data until a restaurant is created/updated/deleted.
 * @param {{ per_page?: number, page?: number }} params - per_page 1–50 default 15, page default 1
 * @returns {Promise<{ data: object[], meta: { current_page, last_page, per_page, total } }>}
 */
export async function listRestaurants(params = {}) {
  const perPage = Math.min(50, Math.max(1, Number(params.per_page) || 15))
  const page = Math.max(1, Number(params.page) || 1)
  const cacheKey = `restaurants:list:${page}:${perPage}`
  const cached = restaurantsListCache.get(cacheKey)
  if (cached != null) {
    return { ...cached, data: [...(cached.data ?? [])], meta: cached.meta ? { ...cached.meta } : undefined }
  }
  const { data } = await api.get('/restaurants', { params: { per_page: perPage, page } })
  restaurantsListCache.set(cacheKey, data)
  return data
}

/**
 * Invalidate restaurants list cache (all pages). Safe to call from outside.
 */
export function invalidateRestaurantsListCachePublic() {
  invalidateRestaurantsListCache()
}

/**
 * Get one restaurant by uuid.
 * @param {string} uuid
 * @returns {Promise<{ data: object }>}
 */
export async function getRestaurant(uuid) {
  const { data } = await api.get(`/restaurants/${uuid}`)
  return data
}

/**
 * Create restaurant. Invalidates restaurants list cache.
 * @param {object} payload - name (required), slug, address, latitude, longitude, phone, email, website, social_links
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function createRestaurant(payload) {
  const { data } = await api.post('/restaurants', payload)
  invalidateRestaurantsListCache()
  return data
}

/**
 * Update restaurant. Invalidates restaurants list cache.
 * @param {string} uuid
 * @param {object} payload - same fields as create (all optional)
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateRestaurant(uuid, payload) {
  const { data } = await api.patch(`/restaurants/${uuid}`, payload)
  invalidateRestaurantsListCache()
  return data
}

/**
 * Delete restaurant. Invalidates restaurants list cache.
 * @param {string} uuid
 * @returns {Promise<void>}
 */
export async function deleteRestaurant(uuid) {
  await api.delete(`/restaurants/${uuid}`)
  invalidateRestaurantsListCache()
}

/**
 * Upload logo. multipart/form-data, field "file". Image jpeg/png/gif/webp, max 2MB.
 * @param {string} uuid
 * @param {File} file
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function uploadLogo(uuid, file) {
  const form = new FormData()
  form.append('file', file)
  const { data } = await api.post(`/restaurants/${uuid}/logo`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

/**
 * Upload banner. Same as logo.
 * @param {string} uuid
 * @param {File} file
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function uploadBanner(uuid, file) {
  const form = new FormData()
  form.append('file', file)
  const { data } = await api.post(`/restaurants/${uuid}/banner`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

// --- Restaurant languages (install / uninstall) ---

/**
 * List installed locales for a restaurant.
 * @param {string} uuid - Restaurant uuid
 * @returns {Promise<{ data: string[] }>}
 */
export async function getRestaurantLanguages(uuid) {
  const { data } = await api.get(`/restaurants/${uuid}/languages`)
  return data
}

/**
 * Add a language. Body: { locale: "nl" }.
 * @param {string} uuid
 * @param {{ locale: string }} payload
 * @returns {Promise<{ message: string, data: string[] }>}
 */
export async function addRestaurantLanguage(uuid, payload) {
  const { data } = await api.post(`/restaurants/${uuid}/languages`, payload)
  return data
}

/**
 * Remove a language. Fails if locale is the restaurant's default_locale.
 * @param {string} uuid
 * @param {string} locale
 * @returns {Promise<void>} 204 on success
 */
export async function removeRestaurantLanguage(uuid, locale) {
  await api.delete(`/restaurants/${uuid}/languages/${encodeURIComponent(locale)}`)
}

// --- Restaurant translations (description per locale) ---

/**
 * Get all description translations for a restaurant.
 * @param {string} uuid
 * @returns {Promise<{ data: Record<string, { description: string | null }> }>}
 */
export async function getRestaurantTranslations(uuid) {
  const { data } = await api.get(`/restaurants/${uuid}/translations`)
  return data
}

/**
 * Get description for one locale.
 * @param {string} uuid
 * @param {string} locale
 * @returns {Promise<{ data: { description: string | null } }>}
 */
export async function getRestaurantTranslation(uuid, locale) {
  const { data } = await api.get(`/restaurants/${uuid}/translations/${encodeURIComponent(locale)}`)
  return data
}

/**
 * Create or update description for a locale. Body: { description: "..." }.
 * @param {string} uuid
 * @param {string} locale
 * @param {{ description: string | null }} payload
 * @returns {Promise<{ message: string, data: { description: string | null } }>}
 */
export async function putRestaurantTranslation(uuid, locale, payload) {
  const { data } = await api.put(`/restaurants/${uuid}/translations/${encodeURIComponent(locale)}`, payload)
  return data
}

// --- Menus ---

/**
 * List menus for a restaurant.
 * @param {string} uuid - Restaurant uuid
 * @returns {Promise<{ data: Array<{ uuid: string, name: string | null, is_active: boolean, sort_order: number, created_at: string, updated_at: string }> }>}
 */
export async function listMenus(uuid) {
  const { data } = await api.get(`/restaurants/${uuid}/menus`)
  return data
}

/**
 * Create menu. Body: { name?, is_active?, sort_order? }.
 * @param {string} uuid - Restaurant uuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function createMenu(uuid, payload) {
  const { data } = await api.post(`/restaurants/${uuid}/menus`, payload)
  return data
}

/**
 * Update menu (name, is_active, sort_order).
 * @param {string} restaurantUuid
 * @param {string} menuUuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateMenu(restaurantUuid, menuUuid, payload) {
  const { data } = await api.patch(`/restaurants/${restaurantUuid}/menus/${menuUuid}`, payload)
  return data
}

/**
 * Delete menu.
 * @param {string} restaurantUuid
 * @param {string} menuUuid
 * @returns {Promise<void>} 204
 */
export async function deleteMenu(restaurantUuid, menuUuid) {
  await api.delete(`/restaurants/${restaurantUuid}/menus/${menuUuid}`)
}

/**
 * Reorder menus. Body: { order: string[] } (array of menu uuids).
 * @param {string} restaurantUuid
 * @param {string[]} order
 * @returns {Promise<{ message: string }>}
 */
export async function reorderMenus(restaurantUuid, order) {
  const { data } = await api.post(`/restaurants/${restaurantUuid}/menus/reorder`, { order })
  return data
}

// --- Categories (per menu) ---

// --- Categories (with list cache: see docs/FRONTEND-SERVICE-CACHE.md) ---

/** In-memory cache: key "categories:{restaurantUuid}:{menuUuid}" -> { data: categories[] }. Cleared on create/update/delete/reorder for that menu. */
const categoriesListCache = new Map()

function categoriesCacheKey(restaurantUuid, menuUuid) {
  const r = restaurantUuid && String(restaurantUuid).trim() ? String(restaurantUuid) : null
  const m = menuUuid && String(menuUuid).trim() ? String(menuUuid) : null
  return r && m ? `categories:${r}:${m}` : null
}

function invalidateCategoriesCache(restaurantUuid, menuUuid) {
  const key = categoriesCacheKey(restaurantUuid, menuUuid)
  if (key) categoriesListCache.delete(key)
}

/**
 * List categories for a menu. Uses cache; next read after mutate fetches again.
 * @param {string} restaurantUuid
 * @param {string} menuUuid
 * @returns {Promise<{ data: Array<{ uuid: string, sort_order: number, translations: Record<string, { name: string }>, created_at: string, updated_at: string }> }>}
 */
export async function listCategories(restaurantUuid, menuUuid) {
  const cacheKey = categoriesCacheKey(restaurantUuid, menuUuid)
  if (cacheKey) {
    const cached = categoriesListCache.get(cacheKey)
    if (cached != null && Array.isArray(cached.data)) {
      return { data: [...cached.data] }
    }
  }
  const { data } = await api.get(`/restaurants/${restaurantUuid}/menus/${menuUuid}/categories`)
  const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
  const listCopy = [...list]
  if (cacheKey) categoriesListCache.set(cacheKey, { data: listCopy })
  return { data: listCopy }
}

/**
 * Create category. Body: { sort_order?, translations: { locale: { name } } }. Invalidates categories list cache for this menu.
 * @param {string} restaurantUuid
 * @param {string} menuUuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function createCategory(restaurantUuid, menuUuid, payload) {
  const { data } = await api.post(`/restaurants/${restaurantUuid}/menus/${menuUuid}/categories`, payload)
  invalidateCategoriesCache(restaurantUuid, menuUuid)
  return data
}

/**
 * Update category (sort_order, translations). Invalidates categories list cache for this menu.
 * @param {string} restaurantUuid
 * @param {string} menuUuid
 * @param {string} categoryUuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateCategory(restaurantUuid, menuUuid, categoryUuid, payload) {
  const { data } = await api.patch(`/restaurants/${restaurantUuid}/menus/${menuUuid}/categories/${categoryUuid}`, payload)
  invalidateCategoriesCache(restaurantUuid, menuUuid)
  return data
}

/**
 * Delete category. Invalidates categories list cache for this menu.
 * @param {string} restaurantUuid
 * @param {string} menuUuid
 * @param {string} categoryUuid
 * @returns {Promise<void>} 204
 */
export async function deleteCategory(restaurantUuid, menuUuid, categoryUuid) {
  await api.delete(`/restaurants/${restaurantUuid}/menus/${menuUuid}/categories/${categoryUuid}`)
  invalidateCategoriesCache(restaurantUuid, menuUuid)
}

/**
 * Reorder categories. Body: { order: string[] }. Invalidates categories list cache for this menu.
 * @param {string} restaurantUuid
 * @param {string} menuUuid
 * @param {string[]} order
 * @returns {Promise<{ message: string }>}
 */
export async function reorderCategories(restaurantUuid, menuUuid, order) {
  const { data } = await api.post(`/restaurants/${restaurantUuid}/menus/${menuUuid}/categories/reorder`, { order })
  invalidateCategoriesCache(restaurantUuid, menuUuid)
  return data
}

/**
 * Reorder menu items within a category. Body: { order: string[] } (item uuids).
 * @param {string} restaurantUuid
 * @param {string} categoryUuid
 * @param {string[]} order
 * @returns {Promise<{ message: string }>}
 */
export async function reorderMenuItems(restaurantUuid, categoryUuid, order) {
  const { data } = await api.post(`/restaurants/${restaurantUuid}/categories/${categoryUuid}/menu-items/reorder`, { order })
  invalidateMenuItemsCache(restaurantUuid)
  invalidateUserMenuItemsListCache()
  return data
}

// --- Menu items (with list cache: see docs/FRONTEND-SERVICE-CACHE.md) ---

/** In-memory cache: restaurant uuid -> { data: menuItems[] }. Cleared on create/update/delete/reorder for that restaurant. */
const menuItemsListCache = new Map()

/**
 * List menu items for a restaurant (ordered by sort_order). Uses cache; next read after mutate fetches again.
 * @param {string} uuid - Restaurant uuid
 * @returns {Promise<{ data: Array<{ uuid: string, category_uuid: string | null, sort_order: number, translations: Record<string, { name: string, description: string | null }>, created_at: string, updated_at: string }> }>}
 */
export async function listMenuItems(uuid) {
  const cacheKey = uuid && String(uuid).trim() ? String(uuid) : null
  if (cacheKey) {
    const cached = menuItemsListCache.get(cacheKey)
    if (cached != null && Array.isArray(cached.data)) {
      return { data: [...cached.data] }
    }
  }
  const { data } = await api.get(`/restaurants/${uuid}/menu-items`)
  const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
  const listCopy = [...list]
  if (cacheKey) menuItemsListCache.set(cacheKey, { data: listCopy })
  return { data: listCopy }
}

/**
 * Invalidate menu items list cache for a restaurant (e.g. after create/update/delete/reorder). Safe to call from outside.
 * @param {string} restaurantUuid
 */
export function invalidateMenuItemsCache(restaurantUuid) {
  const key = restaurantUuid && String(restaurantUuid).trim() ? String(restaurantUuid) : null
  if (key) menuItemsListCache.delete(key)
}

/**
 * Get one menu item.
 * @param {string} uuid - Restaurant uuid
 * @param {string} itemUuid - Menu item uuid
 */
export async function getMenuItem(uuid, itemUuid) {
  const { data } = await api.get(`/restaurants/${uuid}/menu-items/${itemUuid}`)
  return data
}

/**
 * Create menu item. Body: { sort_order: number, translations: { en: { name, description }, ... } }.
 * Invalidates menu items list cache for this restaurant.
 * @param {string} uuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function createMenuItem(uuid, payload) {
  const { data } = await api.post(`/restaurants/${uuid}/menu-items`, payload)
  invalidateMenuItemsCache(uuid)
  invalidateUserMenuItemsListCache()
  return data
}

/**
 * Update menu item (sort_order and/or translations). Invalidates menu items list cache for this restaurant.
 * @param {string} uuid
 * @param {string} itemUuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateMenuItem(uuid, itemUuid, payload) {
  const { data } = await api.patch(`/restaurants/${uuid}/menu-items/${itemUuid}`, payload)
  invalidateMenuItemsCache(uuid)
  invalidateUserMenuItemsListCache()
  return data
}

/**
 * Delete menu item. Invalidates menu items list cache for this restaurant.
 * @param {string} uuid
 * @param {string} itemUuid
 * @returns {Promise<void>} 204
 */
export async function deleteMenuItem(uuid, itemUuid) {
  await api.delete(`/restaurants/${uuid}/menu-items/${itemUuid}`)
  invalidateMenuItemsCache(uuid)
  invalidateUserMenuItemsListCache()
}

export const restaurantService = {
  list: listRestaurants,
  get: getRestaurant,
  create: createRestaurant,
  update: updateRestaurant,
  delete: deleteRestaurant,
  invalidateRestaurantsListCache: invalidateRestaurantsListCachePublic,
  uploadLogo,
  uploadBanner,
  getLanguages: getRestaurantLanguages,
  addLanguage: addRestaurantLanguage,
  removeLanguage: removeRestaurantLanguage,
  getTranslations: getRestaurantTranslations,
  getTranslation: getRestaurantTranslation,
  putTranslation: putRestaurantTranslation,
  listMenus,
  createMenu,
  updateMenu,
  deleteMenu,
  reorderMenus,
  listCategories,
  createCategory,
  updateCategory,
  deleteCategory,
  reorderCategories,
  invalidateCategoriesCache,
  reorderMenuItems,
  listMenuItems,
  getMenuItem,
  createMenuItem,
  updateMenuItem,
  deleteMenuItem,
  invalidateMenuItemsCache,
}

export default restaurantService
