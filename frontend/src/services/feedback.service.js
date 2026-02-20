/**
 * Feedback API service.
 * Public: POST submit (no auth, rate-limited 10/min).
 * Owner: GET list, PATCH (is_approved), DELETE. All use uuid for restaurant and feedback.
 */

import api from './api'

/**
 * Submit feedback (public, no auth). Rate limit 10/min per IP.
 * @param {string} slug - Restaurant slug
 * @param {{ rating: number, text: string, name: string }} payload - rating 1-5, text (required), name (required)
 * @returns {Promise<{ message: string, data: object }>} 201; 422 validation, 404 not found, 429 rate limit
 */
export async function submitFeedback(slug, payload) {
  const { data } = await api.post(`/public/restaurants/${encodeURIComponent(slug)}/feedback`, payload)
  return data
}

/**
 * List feedbacks for a restaurant (owner). Bearer + verified.
 * @param {string} restaurantUuid - Restaurant uuid
 * @returns {Promise<{ data: object[] }>} Newest first
 */
export async function listFeedbacks(restaurantUuid) {
  const { data } = await api.get(`/restaurants/${restaurantUuid}/feedbacks`)
  return data
}

/**
 * Update feedback (approve/reject). Body: { is_approved: boolean }.
 * @param {string} restaurantUuid
 * @param {string} feedbackUuid
 * @param {{ is_approved: boolean }} payload
 * @returns {Promise<{ message: string, data: object }>}
 */
export async function updateFeedback(restaurantUuid, feedbackUuid, payload) {
  const { data } = await api.patch(
    `/restaurants/${restaurantUuid}/feedbacks/${feedbackUuid}`,
    payload
  )
  return data
}

/**
 * Delete feedback (owner).
 * @param {string} restaurantUuid
 * @param {string} feedbackUuid
 * @returns {Promise<void>} 204
 */
export async function deleteFeedback(restaurantUuid, feedbackUuid) {
  await api.delete(`/restaurants/${restaurantUuid}/feedbacks/${feedbackUuid}`)
}

export const feedbackService = {
  submitFeedback,
  listFeedbacks,
  updateFeedback,
  deleteFeedback,
}

export default feedbackService
