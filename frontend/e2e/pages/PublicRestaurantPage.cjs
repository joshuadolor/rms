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

  /** Assert the "Not Available" pill/badge is visible on the public menu (for items with is_available: false). */
  async expectNotAvailablePillVisible() {
    await expect(this.page.getByText('Not Available')).toBeVisible()
  }

  /** Assert the "Not Available" pill is not visible on the page. */
  async expectNotAvailablePillNotVisible() {
    await expect(this.page.getByText('Not Available')).not.toBeVisible()
  }

  /** Assert a tag icon with the given tooltip text (title) is visible on the public menu. */
  async expectTagIconWithTitleVisible(tagText) {
    await expect(this.page.getByTitle(tagText)).toBeVisible()
  }

  /** Assert a menu item with the given name is visible (in the menu list). */
  async expectMenuItemNameVisible(itemName) {
    await expect(this.page.getByRole('heading', { name: itemName, level: 3 })).toBeVisible()
  }
}

module.exports = { PublicRestaurantPage }
