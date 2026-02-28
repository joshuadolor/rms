/**
 * Owner dashboard API. See docs/API-REFERENCE.md — Owner dashboard stats.
 */

import api from './api'
import DashboardStats from '@/models/DashboardStats'

/**
 * GET /api/dashboard/stats — restaurants_count, menu_items_count, feedbacks_total, feedbacks_approved, feedbacks_rejected.
 * @returns {Promise<DashboardStats>}
 */
export async function getStats() {
  const { data } = await api.get('/dashboard/stats')
  return DashboardStats.fromApi(data)
}

export const dashboardService = {
  getStats,
}

export default dashboardService
