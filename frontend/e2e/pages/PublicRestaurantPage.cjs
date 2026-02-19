// @ts-check
/**
 * Page object for the public restaurant page (/r/:slug). Used to assert
 * visible sections such as Opening hours when operating_hours are set.
 */
const { expect } = require('@playwright/test')

class PublicRestaurantPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to the public restaurant page by slug (path /r/:slug). */
  async goToPublicBySlug(slug) {
    await this.page.goto(`/r/${slug}`)
    await expect(this.page.locator('.public-restaurant-page')).toBeVisible({ timeout: 10000 })
  }

  /** Assert the "Opening hours" section (heading and content) is visible. */
  async expectOpeningHoursSectionVisible() {
    await expect(this.page.getByRole('heading', { name: 'Opening hours' })).toBeVisible()
  }

  /** Assert the "Opening hours" section is not present (e.g. when no hours set). */
  async expectOpeningHoursSectionNotVisible() {
    await expect(this.page.getByRole('heading', { name: 'Opening hours' })).not.toBeVisible()
  }
}

module.exports = { PublicRestaurantPage }
