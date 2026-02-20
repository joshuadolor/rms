/**
 * Owner feedback (feature requests) API service.
 * Bearer + verified. See docs/API-REFERENCE.md — Owner feedback (feature requests).
 */

import api, { normalizeApiError } from './api'
import OwnerFeedback from '@/models/OwnerFeedback'

/**
 * POST /api/owner-feedback — submit a feature request.
 * @param {{ message: string, title?: string, restaurant?: string }} payload - message required (max 65535), title optional (max 255), restaurant optional (uuid)
 * @returns {Promise<OwnerFeedback>} 201; 422 validation, 403 if restaurant not owned
 */
export async function submitFeedback(payload) {
  const { data } = await api.post('/owner-feedback', payload)
  const item = data?.data ?? data
  return OwnerFeedback.fromApi(item ?? {})
}

/**
 * GET /api/owner-feedback — list current user's submissions (newest first).
 * @returns {Promise<OwnerFeedback[]>}
 */
export async function listMyFeedbacks() {
  const { data } = await api.get('/owner-feedback')
  const list = data?.data ?? data ?? []
  return Array.isArray(list) ? list.map((item) => OwnerFeedback.fromApi(item)) : []
}

export const ownerFeedbackService = {
  submitFeedback,
  listMyFeedbacks,
}

export default ownerFeedbackService
