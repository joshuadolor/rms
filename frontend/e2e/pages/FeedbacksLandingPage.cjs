// @ts-check
/**
 * Page object for the Feedbacks landing page (/app/feedbacks).
 * Owner chooses a restaurant or is redirected when they have exactly one.
 */
const { expect } = require('@playwright/test')

class FeedbacksLandingPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to the Feedbacks landing page. */
  async goTo() {
    await this.page.goto('/app/feedbacks')
    await expect(this.page.getByRole('heading', { name: 'Feedbacks' })).toBeVisible({ timeout: 10000 })
  }

  /** Navigate to Feedbacks via the app sidebar link (must be logged in). */
  async goToViaSidenav() {
    await this.page.getByRole('link', { name: /feedbacks/i }).click()
    await expect(this.page).toHaveURL(/\/app\/feedbacks/)
    await expect(this.page.getByRole('heading', { name: 'Feedbacks' })).toBeVisible({ timeout: 10000 })
  }

  /** Assert the empty state when the user has no restaurants. */
  async expectEmptyStateNoRestaurants() {
    await expect(
      this.page.getByText('Create a restaurant first to receive and manage feedbacks.')
    ).toBeVisible()
    await expect(this.page.getByRole('link', { name: 'Add restaurant' })).toBeVisible()
  }

  /** Assert that a single restaurant causes redirect (redirecting message may be brief). */
  async expectRedirectToFeedbacksList() {
    await expect(this.page).toHaveURL(/\/app\/feedbacks\/restaurants\/[^/]+/)
  }

  /** Assert that a restaurant link with the given name is visible. */
  async expectRestaurantLinkVisible(restaurantName) {
    await expect(
      this.page.getByRole('link', { name: new RegExp(restaurantName, 'i') })
    ).toBeVisible()
  }

  /** Click the restaurant link to go to its feedback list (by restaurant name in the card). */
  async goToRestaurantFeedbacks(restaurantName) {
    await this.page.getByRole('link', { name: new RegExp(restaurantName, 'i') }).click()
    await expect(this.page).toHaveURL(/\/app\/feedbacks\/restaurants\/[^/]+/)
  }
}

module.exports = { FeedbacksLandingPage }
