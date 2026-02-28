/**
 * Owner dashboard stats — GET /api/dashboard/stats response.
 */

export default class DashboardStats {
  constructor(data = {}) {
    this._restaurantsCount = data.restaurants_count ?? 0
    this._menuItemsCount = data.menu_items_count ?? 0
    this._feedbacksTotal = data.feedbacks_total ?? 0
    this._feedbacksApproved = data.feedbacks_approved ?? 0
    this._feedbacksRejected = data.feedbacks_rejected ?? 0
  }

  get restaurantsCount() {
    return this._restaurantsCount
  }

  get menuItemsCount() {
    return this._menuItemsCount
  }

  get feedbacksTotal() {
    return this._feedbacksTotal
  }

  get feedbacksApproved() {
    return this._feedbacksApproved
  }

  get feedbacksRejected() {
    return this._feedbacksRejected
  }

  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new DashboardStats(data ?? {})
  }
}
