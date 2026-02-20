// @ts-check
/**
 * Page object for the Feedbacks list page (/app/feedbacks/restaurants/:restaurantUuid).
 * Owner sees list, approves/rejects/deletes feedback. Delete triggers window.confirm — caller must handle dialog.
 */
const { expect } = require('@playwright/test')

class FeedbacksListPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to the feedbacks list for a restaurant. */
  async goTo(restaurantUuid) {
    await this.page.goto(`/app/feedbacks/restaurants/${restaurantUuid}`)
    await expect(this.page.getByRole('heading', { name: 'Feedbacks' })).toBeVisible({ timeout: 10000 })
  }

  /** Assert the list heading and subtitle are visible. */
  async expectFeedbacksListHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Feedbacks' })).toBeVisible()
    await expect(
      this.page.getByText(/Approve feedback to show it on your public page/)
    ).toBeVisible()
  }

  /** Assert the empty state (no feedbacks yet). */
  async expectEmptyState() {
    await expect(
      this.page.getByText('No feedbacks yet. They will appear here when customers submit reviews.')
    ).toBeVisible()
  }

  /** Feedback list container (avoids matching other listitems on the page). */
  get _feedbacksList() {
    return this.page.getByTestId('feedbacks-list')
  }

  /** Assert that a feedback card containing the given text (or name) is visible. */
  async expectFeedbackVisible(content) {
    await expect(this.page.getByText(content)).toBeVisible()
  }

  /** Assert the number of feedback list items. */
  async expectFeedbackCount(count) {
    await expect(this._feedbacksList.getByTestId('feedback-item')).toHaveCount(count)
  }

  /**
   * Click Approve for the feedback that contains the given text (in message or name).
   * Use when the feedback is in "Pending" state.
   */
  async clickApproveForFeedbackWithContent(content) {
    const card = this._feedbacksList.getByTestId('feedback-item').filter({ hasText: content })
    await card.getByRole('button', { name: 'Approve feedback' }).click()
  }

  /**
   * Click Reject for the feedback that contains the given text.
   * Use when the feedback is in "Approved" state.
   */
  async clickRejectForFeedbackWithContent(content) {
    const card = this._feedbacksList.getByTestId('feedback-item').filter({ hasText: content })
    await card.getByRole('button', { name: 'Reject feedback' }).click()
  }

  /**
   * Click Delete for the feedback that contains the given text.
   * Triggers window.confirm — test must call page.on('dialog', d => d.accept()) before this.
   */
  async clickDeleteForFeedbackWithContent(content) {
    const card = this._feedbacksList.getByTestId('feedback-item').filter({ hasText: content })
    await card.getByRole('button', { name: 'Delete feedback' }).click()
  }

  /** Assert that a success toast with the given message (or pattern) is visible. */
  async expectToastSuccess(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('status').filter({ hasText: pattern })).toBeVisible({
      timeout: 5000,
    })
  }

  /** Assert Back link to Feedbacks is visible. */
  async expectBackLinkVisible() {
    await expect(this.page.getByRole('link', { name: /back/i })).toBeVisible()
  }
}

module.exports = { FeedbacksListPage }
