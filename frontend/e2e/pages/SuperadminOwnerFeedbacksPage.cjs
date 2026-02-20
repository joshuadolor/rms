// @ts-check
/**
 * Page object for the Superadmin Owner feedbacks page (/app/superadmin/owner-feedbacks).
 * Lists all owner feedbacks; superadmin can toggle status (Mark reviewed / Mark pending).
 * All selectors encapsulated; no raw locators in tests.
 */
const { expect } = require('@playwright/test')

class SuperadminOwnerFeedbacksPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  _container() {
    return this.page.getByTestId('superadmin-owner-feedbacks-page')
  }

  _feedbacksList() {
    return this.page.getByTestId('superadmin-owner-feedbacks-list')
  }

  /** Navigate to the Owner feedbacks page by URL. Caller must be logged in as superadmin. */
  async goTo() {
    await this.page.goto('/app/superadmin/owner-feedbacks')
    await expect(this._container()).toBeVisible({ timeout: 10000 })
  }

  /** Assert the Owner feedbacks page is visible (heading and description). */
  async expectPageVisible() {
    await expect(this._container()).toBeVisible()
    await expect(
      this.page.getByRole('heading', { name: 'Owner feedbacks', level: 2 })
    ).toBeVisible()
    await expect(
      this.page.getByText(/Feature requests and feedback from restaurant owners/i)
    ).toBeVisible()
  }

  /** Assert the empty state (no owner feedbacks yet) is visible. */
  async expectEmptyState() {
    await expect(this.page.getByText('No owner feedbacks yet.')).toBeVisible()
  }

  /** Assert the feedback list is visible and has at least one row. */
  async expectFeedbacksListVisible() {
    await expect(this._feedbacksList()).toBeVisible()
  }

  /** Assert a feedback row contains the given submitter name or email. */
  async expectFeedbackRowWithSubmitterVisible(submitterNameOrEmail) {
    await expect(this._feedbacksList().getByText(submitterNameOrEmail)).toBeVisible()
  }

  /** Assert a feedback row contains the given message (or truncated) text. */
  async expectFeedbackRowWithMessageVisible(messageContent) {
    await expect(this._feedbacksList().getByText(messageContent)).toBeVisible()
  }

  _rowContaining(messageContent) {
    return this._feedbacksList()
      .locator('[data-testid^="superadmin-feedback-row-"]')
      .filter({ hasText: messageContent })
  }

  /** Assert a feedback row shows the given status badge (e.g. "Pending" or "Reviewed"). */
  async expectFeedbackRowShowsStatus(messageContent, status) {
    await expect(this._rowContaining(messageContent).getByText(status)).toBeVisible()
  }

  /**
   * Click "Mark reviewed" for the feedback row that contains the given message/text.
   * Use when the row is currently "Pending".
   */
  async clickMarkReviewedForRowWithMessage(messageContent) {
    await this._rowContaining(messageContent)
      .getByRole('button', { name: 'Mark reviewed' })
      .click()
  }

  /**
   * Click "Mark pending" for the feedback row that contains the given message/text.
   * Use when the row is currently "Reviewed".
   */
  async clickMarkPendingForRowWithMessage(messageContent) {
    await this._rowContaining(messageContent)
      .getByRole('button', { name: 'Mark pending' })
      .click()
  }

  /** Assert a success toast with the given message (or pattern) is visible. */
  async expectToastSuccess(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('status').filter({ hasText: pattern })).toBeVisible({
      timeout: 10000,
    })
  }

  /** Assert the page shows an error alert (e.g. permission or not found). */
  async expectAlertError(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('alert').filter({ hasText: pattern })).toBeVisible()
  }
}

module.exports = { SuperadminOwnerFeedbacksPage }
