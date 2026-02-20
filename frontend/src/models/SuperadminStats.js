/**
 * Superadmin dashboard stats — shapes GET /api/superadmin/stats response.
 * Use SuperadminStats.fromApi(response.data) when consuming the stats endpoint.
 */

export default class SuperadminStats {
  constructor(data = {}) {
    this._restaurantsCount = data.restaurants_count ?? 0
    this._usersCount = data.users_count ?? 0
    this._paidUsersCount = data.paid_users_count ?? 0
  }

  get restaurantsCount() {
    return this._restaurantsCount
  }

  get usersCount() {
    return this._usersCount
  }

  get paidUsersCount() {
    return this._paidUsersCount
  }

  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new SuperadminStats(data ?? {})
  }
}
