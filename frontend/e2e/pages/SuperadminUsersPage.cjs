// @ts-check
/**
 * Page object for the Superadmin Users page (list and paid/active toggles).
 * All selectors and interactions encapsulated.
 */
const { expect } = require('@playwright/test')

class SuperadminUsersPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Assert Users page is visible (heading). */
  async expectUsersHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Users' })).toBeVisible()
  }

  /** Assert the users list container is visible (allow time for API load). */
  async expectUserListVisible() {
    await expect(this.page.getByTestId('superadmin-users-list')).toBeVisible({ timeout: 15000 })
  }

  /** Assert a user row with the given email is visible (allow time for API load). */
  async expectUserRowWithEmailVisible(email) {
    await expect(this.page.getByTestId(`superadmin-user-row-${email}`)).toBeVisible({ timeout: 15000 })
  }

  /** Assert "No users found" is visible. */
  async expectNoUsersFound() {
    await expect(this.page.getByText('No users found.')).toBeVisible()
  }

  /** Click "Mark paid" (or "Paid" to toggle off) for the user row with the given email. */
  async clickPaidToggleForUser(email) {
    const row = this.page.getByTestId(`superadmin-user-row-${email}`)
    await row.getByRole('button', { name: /mark paid|remove paid|^Paid$|^Free$/i }).click()
  }

  /** Click "Deactivate" (or "Activate") for the user row with the given email. */
  async clickActiveToggleForUser(email) {
    const row = this.page.getByTestId(`superadmin-user-row-${email}`)
    await row.getByRole('button', { name: /deactivate user|activate user|^Deactivate$|^Activate$/i }).click()
  }

  /** Assert an alert or inline error contains the given text. */
  async expectAlertWithText(text) {
    await expect(this.page.getByRole('alert').filter({ hasText: text })).toBeVisible()
  }

  /** Assert the user row with the given email shows the "Paid" badge/state. */
  async expectUserRowShowsPaid(email) {
    const row = this.page.getByTestId(`superadmin-user-row-${email}`)
    await expect(row).toContainText('Paid')
  }
}

module.exports = { SuperadminUsersPage }
