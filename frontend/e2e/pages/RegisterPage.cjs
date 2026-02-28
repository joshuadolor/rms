// @ts-check
/**
 * Page object for the Register page (single-step form).
 * All selectors and interactions encapsulated; tests must not use element querying.
 */
const { expect } = require('@playwright/test')

class RegisterPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  async goto() {
    await this.page.goto('/register', { waitUntil: 'domcontentloaded' })
    await this.page.getByTestId('register-form').waitFor({ state: 'visible', timeout: 15000 })
  }

  async fillName(name) {
    await this.page.getByPlaceholder(/jane smith/i).fill(name)
  }

  async fillEmail(email) {
    await this.page.getByPlaceholder(/you@example\.com/i).fill(email)
  }

  async fillPassword(password) {
    await this.page.getByLabel(/^password$/i).fill(password)
  }

  async fillConfirmPassword(password) {
    await this.page.getByLabel(/confirm password/i).fill(password)
  }

  async checkTerms() {
    await this.page.getByRole('checkbox', { name: /terms of service|privacy policy|agree/i }).check()
  }

  async submit() {
    await this.page.getByRole('button', { name: /create account/i }).click()
  }

  /**
   * Fill all fields and submit. Caller must have set up API mocks if needed.
   * @param {{ name: string, email: string, password: string }} opts
   */
  async fillAndSubmit(opts) {
    await this.fillName(opts.name)
    await this.fillEmail(opts.email)
    await this.fillPassword(opts.password)
    await this.fillConfirmPassword(opts.password)
    await this.checkTerms()
    await this.submit()
  }

  /** Assert the registration form is visible (single-step: all fields on one page). */
  async expectRegistrationFormVisible() {
    await expect(this.page.getByTestId('register-heading')).toBeVisible()
    await expect(this.page.getByTestId('register-form')).toBeVisible()
    await expect(this.page.getByPlaceholder(/jane smith/i)).toBeVisible()
    await expect(this.page.getByPlaceholder(/you@example\.com/i)).toBeVisible()
    await expect(this.page.getByLabel(/^password$/i)).toBeVisible()
    await expect(this.page.getByLabel(/confirm password/i)).toBeVisible()
    await expect(this.page.getByRole('checkbox', { name: /terms|privacy|agree/i })).toBeVisible()
    await expect(this.page.getByRole('button', { name: /create account/i })).toBeVisible()
  }

  /** Assert form-level error area contains the given text. */
  async expectFormError(textOrRegex) {
    await expect(this.page.locator('#register-error')).toContainText(textOrRegex)
  }

  /** Assert a validation error message is visible (e.g. field-level "Please enter your name."). */
  async expectValidationError(textOrRegex) {
    await expect(this.page.getByRole('alert').filter({ hasText: textOrRegex })).toBeVisible()
  }

  /** Assert redirect to verify-email page with success heading. */
  async expectRedirectedToVerifyEmail() {
    await expect(this.page).toHaveURL(/\/verify-email/)
    await expect(this.page.getByRole('heading', { name: 'Check your email' })).toBeVisible()
  }
}

module.exports = { RegisterPage }
