/**
 * Superadmin API service. All endpoints require Bearer + verified + superadmin (403 if not).
 * See docs/API-REFERENCE.md Superadmin section.
 */

import api, { normalizeApiError } from './api'
import User from '@/models/User'
import Restaurant from '@/models/Restaurant'
import SuperadminStats from '@/models/SuperadminStats'
import OwnerFeedback from '@/models/OwnerFeedback'

/**
 * GET /api/superadmin/stats — restaurants_count, users_count, paid_users_count.
 * @returns {Promise<SuperadminStats>}
 */
export async function getStats() {
  const { data } = await api.get('/superadmin/stats')
  return SuperadminStats.fromApi(data)
}

/**
 * GET /api/superadmin/users — list all users (uuid, name, email, email_verified_at, pending_email, is_paid, is_active, is_superadmin).
 * @returns {Promise<User[]>}
 */
export async function listUsers() {
  const { data } = await api.get('/superadmin/users')
  const list = data?.data ?? data ?? []
  return Array.isArray(list) ? list.map((u) => User.fromApi(u)) : []
}

/**
 * PATCH /api/superadmin/users/{user} — optional is_paid, is_active. Cannot change own is_active (422).
 * @param {string} userUuid - User's uuid
 * @param {{ is_paid?: boolean, is_active?: boolean }} payload
 * @returns {Promise<User>}
 */
export async function updateUser(userUuid, payload) {
  const { data } = await api.patch(`/superadmin/users/${encodeURIComponent(userUuid)}`, payload)
  const userData = data?.data ?? data
  return User.fromApi(userData ?? {})
}

/**
 * GET /api/superadmin/restaurants — read-only list of all restaurants (same shape as owner list).
 * @returns {Promise<Restaurant[]>}
 */
export async function listRestaurants() {
  const { data } = await api.get('/superadmin/restaurants')
  const list = data?.data ?? data ?? []
  return Array.isArray(list) ? list.map((r) => Restaurant.fromApi(r)) : []
}

/**
 * GET /api/superadmin/owner-feedbacks — list all owner feedbacks (newest first).
 * @returns {Promise<OwnerFeedback[]>}
 */
export async function listOwnerFeedbacks() {
  const { data } = await api.get('/superadmin/owner-feedbacks')
  const list = data?.data ?? data ?? []
  return Array.isArray(list) ? list.map((item) => OwnerFeedback.fromApi(item)) : []
}

/**
 * PATCH /api/superadmin/owner-feedbacks/{uuid} — optional status (pending | reviewed).
 * @param {string} feedbackUuid - Feedback uuid
 * @param {{ status?: 'pending' | 'reviewed' }} payload
 * @returns {Promise<OwnerFeedback>}
 */
export async function updateOwnerFeedback(feedbackUuid, payload) {
  const { data } = await api.patch(`/superadmin/owner-feedbacks/${encodeURIComponent(feedbackUuid)}`, payload)
  const item = data?.data ?? data
  return OwnerFeedback.fromApi(item ?? {})
}

export const superadminService = {
  getStats,
  listUsers,
  updateUser,
  listRestaurants,
  listOwnerFeedbacks,
  updateOwnerFeedback,
}

export default superadminService
