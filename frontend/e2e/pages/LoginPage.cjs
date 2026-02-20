// @ts-check
/**
 * Page object for the Login page. All selectors and interactions encapsulated.
 */
const { expect } = require('@playwright/test')

class LoginPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  async goto() {
    await this.page.goto('/login')
  }

  async fillEmail(email) {
    await this.page.getByPlaceholder(/you@example\.com/i).fill(email)
  }

  async fillPassword(password) {
    await this.page.getByPlaceholder(/••••••••/).fill(password)
  }

  async submit() {
    await this.page.getByRole('button', { name: /sign in/i }).click()
  }

  /** Perform full login flow: email + password + submit. Caller must have navigated to /login and set up mocks. */
  async login(email, password) {
    await this.fillEmail(email)
    await this.fillPassword(password)
    await this.submit()
  }
}

module.exports = { LoginPage }
