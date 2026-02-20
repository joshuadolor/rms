// @ts-check
/**
 * Page object for the Superadmin Dashboard (stats cards and welcome).
 * All selectors and assertions encapsulated.
 */
const { expect } = require('@playwright/test')

class SuperadminDashboardPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Assert superadmin dashboard container is visible. */
  async expectDashboardVisible() {
    await expect(this.page.getByTestId('superadmin-dashboard')).toBeVisible()
  }

  /** Assert Dashboard heading is visible. */
  async expectHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
  }

  /** Assert the three stats cards show expected values (Restaurants, Users, Paid users). */
  async expectStatsCards(restaurantsCount, usersCount, paidUsersCount) {
    const dashboard = this.page.getByTestId('superadmin-dashboard')
    await expect(dashboard).toContainText(String(restaurantsCount))
    await expect(dashboard).toContainText(String(usersCount))
    await expect(dashboard).toContainText(String(paidUsersCount))
  }

  /** Assert welcome message contains the given name (or partial text). */
  async expectWelcomeVisible() {
    await expect(this.page.getByTestId('superadmin-dashboard').getByText(/welcome,/i)).toBeVisible()
  }
}

module.exports = { SuperadminDashboardPage }
