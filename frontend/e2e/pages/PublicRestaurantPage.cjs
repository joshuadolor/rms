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

  // --- Reviews (approved feedbacks) and submit feedback form ---

  /** Assert the Reviews section heading "What people say" is visible. */
  async expectReviewsSectionVisible() {
    await expect(this.page.getByRole('heading', { name: 'What people say' })).toBeVisible()
  }

  /** Assert the empty reviews message is visible. */
  async expectNoReviewsYet() {
    await expect(
      this.page.getByText('No reviews yet. Be the first to leave feedback below.')
    ).toBeVisible()
  }

  /** Assert a review (approved feedback) with the given author name or text snippet is visible. */
  async expectReviewVisible(nameOrText) {
    await expect(this.page.getByText(nameOrText)).toBeVisible()
  }

  /** Assert the "Leave your feedback" form heading is visible. */
  async expectFeedbackFormVisible() {
    await expect(this.page.getByRole('heading', { name: 'Leave your feedback' })).toBeVisible()
    await expect(this.page.getByRole('button', { name: 'Send feedback' })).toBeVisible()
  }

  /** Set rating by clicking the nth star (1–5). */
  async setFeedbackRating(stars) {
    await this.page.getByRole('button', { name: `${stars} star${stars > 1 ? 's' : ''}` }).click()
  }

  /** Fill the "Your name" field in the feedback form. */
  async setFeedbackName(name) {
    await this.page.getByLabel(/your name/i).fill(name)
  }

  /** Fill the "Your message" textarea in the feedback form. */
  async setFeedbackMessage(message) {
    await this.page.getByLabel(/your message/i).fill(message)
  }

  /** Submit the feedback form. */
  async submitFeedbackForm() {
    await this.page.getByRole('button', { name: 'Send feedback' }).click()
  }

  /** Assert the success message (role="status") after submitting feedback is visible. */
  async expectFeedbackSuccessMessage(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('status').filter({ hasText: pattern })).toBeVisible({
      timeout: 5000,
    })
  }

  /** Assert an error message (role="alert") in the feedback form is visible. */
  async expectFeedbackErrorMessage(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('alert').filter({ hasText: pattern })).toBeVisible()
  }

  /** Assert validation error for a specific field (e.g. "Please choose a rating"). */
  async expectFeedbackFieldError(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByText(pattern)).toBeVisible()
  }

  // --- Logo and hero (public restaurant page with logo/banner optimization) ---

  /** Assert the nav logo (image in sticky nav) is visible. Restaurant name is the img alt. */
  async expectNavLogoVisible(restaurantName) {
    const nav = this.page.locator('.public-restaurant-page nav')
    await expect(nav.getByRole('img', { name: restaurantName })).toBeVisible()
  }

  /** Assert the hero logo block (logo above restaurant name in hero) is visible. */
  async expectHeroLogoBlockVisible() {
    await expect(this.page.getByTestId('public-hero-logo')).toBeVisible()
  }

  /** Assert the hero logo block is not present (restaurant has no logo). */
  async expectHeroLogoBlockNotVisible() {
    await expect(this.page.getByTestId('public-hero-logo')).not.toBeVisible()
  }
}

module.exports = { PublicRestaurantPage }
