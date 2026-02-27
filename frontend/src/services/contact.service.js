/**
 * Restaurant contacts API service.
 * Owner: list, get, create, update, delete. All use uuid for restaurant and contact.
 */

import api from './api'

/**
 * List contacts for a restaurant. Bearer + verified.
 * @param {string} restaurantUuid - Restaurant uuid
 * @returns {Promise<{ data: object[] }>}
 */
export async function listContacts(restaurantUuid) {
  const { data } = await api.get(`/restaurants/${restaurantUuid}/contacts`)
  return data
}

/**
 * Get one contact. Bearer + verified.
 * @param {string} restaurantUuid
 * @param {string} contactUuid
 * @returns {Promise<{ data: object }>}
 */
export async function getContact(restaurantUuid, contactUuid) {
  const { data } = await api.get(`/restaurants/${restaurantUuid}/contacts/${contactUuid}`)
  return data
}

/**
 * Create contact. Body: type, value (required; phone or URL), label (optional), is_active (optional, default true).
 * For backward compat the API also accepts number (same as value for phone types).
 * @param {string} restaurantUuid
 * @param {{ type: string, value: string, number?: string | null, label?: string | null, is_active?: boolean }} payload
 * @returns {Promise<{ message: string, data: object }>} 201
 */
export async function createContact(restaurantUuid, payload) {
  const { data } = await api.post(`/restaurants/${restaurantUuid}/contacts`, payload)
  return data
}

/**
 * Update contact. Body: optional type, value, number, label, is_active.
 * @param {string} restaurantUuid
 * @param {string} contactUuid
 * @param {object} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateContact(restaurantUuid, contactUuid, payload) {
  const { data } = await api.patch(
    `/restaurants/${restaurantUuid}/contacts/${contactUuid}`,
    payload
  )
  return data
}

/**
 * Delete contact. Bearer + verified.
 * @param {string} restaurantUuid
 * @param {string} contactUuid
 * @returns {Promise<void>} 204
 */
export async function deleteContact(restaurantUuid, contactUuid) {
  await api.delete(`/restaurants/${restaurantUuid}/contacts/${contactUuid}`)
}

export const contactService = {
  listContacts,
  getContact,
  createContact,
  updateContact,
  deleteContact,
}

export default contactService
