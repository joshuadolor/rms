/**
 * Menu item tags API. List returns default tags only (create/update/delete disabled; 403).
 * Use shared api client. Per docs/API-REFERENCE.md: list returns { data: [ tag payload ] }.
 */

import api from './api'

/**
 * List all tags the user can assign to menu items (default tags only).
 * @returns {Promise<Array<{ uuid: string, color: string, icon: string, text: string, is_default?: boolean }>>}
 */
export async function listMenuItemTags() {
  const { data: body } = await api.get('/menu-item-tags')
  const list = Array.isArray(body?.data) ? body.data : []
  return list
}

export const menuItemTagService = {
  list: listMenuItemTags,
}

export default menuItemTagService
