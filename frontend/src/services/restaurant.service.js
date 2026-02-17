/**
 * Restaurant API service. All endpoints require auth (Bearer token).
 * Uses uuid in paths. Logo/banner URLs are returned by the API (serve at GET /api/restaurants/{uuid}/logo|banner).
 */

import api from './api'

/**
 * List restaurants (paginated).
 * @param {{ per_page?: number, page?: number }} params - per_page 1–50 default 15, page default 1
 * @returns {Promise<{ data: object[], meta: { current_page, last_page, per_page, total } }>}
 */
export async function listRestaurants(params = {}) {
  const perPage = Math.min(50, Math.max(1, Number(params.per_page) || 15))
  const page = Math.max(1, Number(params.page) || 1)
  const { data } = await api.get('/restaurants', { params: { per_page: perPage, page } })
  return data
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
 * Create restaurant.
 * @param {object} payload - name (required), slug, address, latitude, longitude, phone, email, website, social_links
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function createRestaurant(payload) {
  const { data } = await api.post('/restaurants', payload)
  return data
}

/**
 * Update restaurant.
 * @param {string} uuid
 * @param {object} payload - same fields as create (all optional)
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateRestaurant(uuid, payload) {
  const { data } = await api.patch(`/restaurants/${uuid}`, payload)
  return data
}

/**
 * Delete restaurant.
 * @param {string} uuid
 * @returns {Promise<void>}
 */
export async function deleteRestaurant(uuid) {
  await api.delete(`/restaurants/${uuid}`)
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

// --- Menu items ---

/**
 * List menu items for a restaurant (ordered by sort_order).
 * @param {string} uuid - Restaurant uuid
 * @returns {Promise<{ data: Array<{ uuid: string, sort_order: number, translations: Record<string, { name: string, description: string | null }>, created_at: string, updated_at: string }> }>}
 */
export async function listMenuItems(uuid) {
  const { data } = await api.get(`/restaurants/${uuid}/menu-items`)
  return data
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
 * @param {string} uuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function createMenuItem(uuid, payload) {
  const { data } = await api.post(`/restaurants/${uuid}/menu-items`, payload)
  return data
}

/**
 * Update menu item (sort_order and/or translations).
 * @param {string} uuid
 * @param {string} itemUuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateMenuItem(uuid, itemUuid, payload) {
  const { data } = await api.patch(`/restaurants/${uuid}/menu-items/${itemUuid}`, payload)
  return data
}

/**
 * Delete menu item.
 * @param {string} uuid
 * @param {string} itemUuid
 * @returns {Promise<void>} 204
 */
export async function deleteMenuItem(uuid, itemUuid) {
  await api.delete(`/restaurants/${uuid}/menu-items/${itemUuid}`)
}

export const restaurantService = {
  list: listRestaurants,
  get: getRestaurant,
  create: createRestaurant,
  update: updateRestaurant,
  delete: deleteRestaurant,
  uploadLogo,
  uploadBanner,
  getLanguages: getRestaurantLanguages,
  addLanguage: addRestaurantLanguage,
  removeLanguage: removeRestaurantLanguage,
  getTranslations: getRestaurantTranslations,
  getTranslation: getRestaurantTranslation,
  putTranslation: putRestaurantTranslation,
  listMenuItems,
  getMenuItem,
  createMenuItem,
  updateMenuItem,
  deleteMenuItem,
}

export default restaurantService
