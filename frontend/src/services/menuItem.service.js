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
 * List all menu items the user can access (standalone + from their restaurants).
 * Cached; next call returns cache until a menu item is created/updated/deleted.
 * @returns {Promise<{ data: Array<{ uuid: string, restaurant_uuid?: string | null, category_uuid: string | null, sort_order: number, translations: Record<string, { name: string, description: string | null }>, created_at: string, updated_at: string }> }>}
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
 * Body: { sort_order?: number, translations: { locale: { name: string, description?: string | null } } }
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function createStandaloneMenuItem(payload) {
  const { data } = await api.post('/menu-items', payload)
  invalidateUserMenuItemsListCache()
  return data
}

/**
 * Update a menu item (standalone or restaurant). Body: sort_order, translations (optional).
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
