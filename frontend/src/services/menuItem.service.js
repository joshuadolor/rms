/**
 * User-level menu items API (standalone list, create, get, update, delete).
 * Use for the "Menu items" page. All require Bearer + verified.
 * List is cached; see docs/FRONTEND-SERVICE-CACHE.md. Invalidate on create/update/delete (here or from restaurant service).
 */

import api from './api'

const USER_MENU_ITEMS_LIST_KEY = 'user-menu-items:list'
const userMenuItemsListCache = new Map()

/**
 * Invalidate the user menu items list cache (GET /api/menu-items). Call after any create/update/delete of a menu item.
 * Exported so restaurant.service can invalidate when menu items change from the restaurant flow.
 */
export function invalidateUserMenuItemsListCache() {
  userMenuItemsListCache.delete(USER_MENU_ITEMS_LIST_KEY)
}

/**
 * List only standalone (catalog) menu items. Used by the "Menu items" catalog page.
 * Restaurant menu items are listed per restaurant via restaurantService.listMenuItems.
 * Cached; next call returns cache until a menu item is created/updated/deleted.
 * Response shape per docs/API-REFERENCE.md (user-level menu item payload including type, combo_entries, variant_option_groups, variant_skus when applicable).
 * Caller should apply MenuItem.fromApi({ data: item }) per item if using models.
 * @returns {Promise<{ data: Array<object> }>}
 */
export async function listUserMenuItems() {
  const cached = userMenuItemsListCache.get(USER_MENU_ITEMS_LIST_KEY)
  if (cached != null && Array.isArray(cached.data)) {
    return { data: [...cached.data] }
  }
  const { data } = await api.get('/menu-items')
  const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
  const listCopy = [...list]
  userMenuItemsListCache.set(USER_MENU_ITEMS_LIST_KEY, { data: listCopy })
  return { data: listCopy }
}

/**
 * Get one menu item (standalone or from a restaurant the user owns).
 * @param {string} itemUuid
 */
export async function getUserMenuItem(itemUuid) {
  const { data } = await api.get(`/menu-items/${itemUuid}`)
  return data
}

/**
 * Create a standalone menu item (not tied to any restaurant).
 * Body per docs/API-REFERENCE.md: sort_order?, type? (simple|combo|with_variants), price? (simple), combo_price?, combo_entries? (when type combo), variant_option_groups?, variant_skus? (when type with_variants), translations (required; at least one name).
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function createStandaloneMenuItem(payload) {
  const { data } = await api.post('/menu-items', payload)
  invalidateUserMenuItemsListCache()
  return data
}

/**
 * Update a menu item (standalone or restaurant). Body per docs/API-REFERENCE.md: sort_order?, translations?, price? (simple), type?, combo_price?, combo_entries? (combo; replaces all), variant_option_groups? and variant_skus? (with_variants; both together).
 * @param {string} itemUuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateUserMenuItem(itemUuid, payload) {
  const { data } = await api.patch(`/menu-items/${itemUuid}`, payload)
  invalidateUserMenuItemsListCache()
  return data
}

/**
 * Delete a menu item (standalone or from a restaurant the user owns).
 * @param {string} itemUuid
 * @returns {Promise<void>} 204
 */
export async function deleteUserMenuItem(itemUuid) {
  await api.delete(`/menu-items/${itemUuid}`)
  invalidateUserMenuItemsListCache()
}

export const menuItemService = {
  list: listUserMenuItems,
  get: getUserMenuItem,
  create: createStandaloneMenuItem,
  update: updateUserMenuItem,
  delete: deleteUserMenuItem,
  invalidateUserMenuItemsListCache,
}

export default menuItemService
