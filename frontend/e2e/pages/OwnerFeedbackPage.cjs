// @ts-check
/**
 * Page object for the Owner Feature request page (/app/owner-feedback).
 * Owner can submit feedback (message required, title/restaurant optional) and see "My feature requests" list.
 * All selectors encapsulated; no raw locators in tests.
 */
const { expect } = require('@playwright/test')

class OwnerFeedbackPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  _container() {
    return this.page.getByTestId('owner-feedback-page')
  }

  _myRequestsList() {
    return this.page.getByTestId('owner-feedback-list')
  }

  /** Navigate to the Feature request page by URL. Caller must be logged in. */
  async goTo() {
    await this.page.goto('/app/owner-feedback')
    await expect(this._container()).toBeVisible({ timeout: 10000 })
  }

  /** Navigate to Feature request via the app sidebar link (must be logged in as non-superadmin). */
  async goToViaSidenav() {
    const sidebar = this.page.getByRole('complementary', { name: 'Main navigation' })
    const menuButton = this.page.getByRole('button', { name: /open menu/i })
    if (await menuButton.isVisible()) {
      await menuButton.click()
    }
    await sidebar.getByRole('link', { name: /feature request/i }).click()
    await expect(this.page).toHaveURL(/\/app\/owner-feedback/)
    await expect(this._container()).toBeVisible({ timeout: 10000 })
  }

  /** Assert the Feature request page (heading and send form) is visible. */
  async expectPageVisible() {
    await expect(this._container()).toBeVisible()
    await expect(this.page.getByRole('heading', { name: 'Feature request', level: 2 })).toBeVisible()
    await expect(this.page.getByRole('heading', { name: 'Send feedback', level: 3 })).toBeVisible()
  }

  /** Set the required message field. */
  async setMessage(value) {
    await this.page.getByLabel(/message/i).fill(value)
  }

  /** Set the optional title field (by label or placeholder). */
  async setTitle(value) {
    await this.page.getByPlaceholder('Short summary').fill(value)
  }

  /** Submit the feedback form (clicks "Send feedback" button). */
  async submitForm() {
    await this.page.getByRole('button', { name: 'Send feedback' }).click()
  }

  /** Assert the message field shows a validation error (inline or role=alert). */
  async expectMessageFieldError(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('alert').filter({ hasText: pattern })).toBeVisible()
  }

  /** Assert a success toast with the given message (or pattern) is visible. */
  async expectToastSuccess(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('status').filter({ hasText: pattern })).toBeVisible({
      timeout: 5000,
    })
  }

  /** Assert the "My feature requests" section is visible (heading; list may be empty and then ul is not in DOM). */
  async expectMyRequestsSectionVisible() {
    await expect(
      this.page.getByRole('heading', { name: 'My feature requests', level: 3 })
    ).toBeVisible()
  }

  /** Assert the "My feature requests" list shows empty state (no submissions yet). */
  async expectEmptyMyRequests() {
    await expect(
      this.page.getByText(
        /No submissions yet\. Use the form above to send your first feature request\./i
      )
    ).toBeVisible()
  }

  /** Assert at least one item in "My feature requests" contains the given text (message or title). Use when list has items. */
  async expectMyRequestWithMessageVisible(content) {
    await expect(this._myRequestsList().getByText(content)).toBeVisible()
  }

  /** Assert the number of items in "My feature requests" list. Use only when list is loaded with items (ul is in DOM). */
  async expectMyRequestCount(count) {
    await expect(this._myRequestsList().locator('li')).toHaveCount(count)
  }
}

module.exports = { OwnerFeedbackPage }
