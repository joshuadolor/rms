// @ts-check
/**
 * Page object for the Superadmin Restaurants page (view-only list).
 * All selectors and assertions encapsulated.
 */
const { expect } = require('@playwright/test')

class SuperadminRestaurantsPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Assert Restaurants page heading is visible. */
  async expectRestaurantsHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Restaurants' })).toBeVisible()
  }

  /** Assert the restaurants list container is visible. */
  async expectRestaurantListVisible() {
    await expect(this.page.getByTestId('superadmin-restaurants-list')).toBeVisible()
  }

  /** Assert "No restaurants found" is visible. */
  async expectNoRestaurantsFound() {
    await expect(this.page.getByText('No restaurants found.')).toBeVisible()
  }
}

module.exports = { SuperadminRestaurantsPage }
