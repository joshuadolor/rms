// @ts-check
/**
 * Page object for the Verify Email page ("Check your email").
 * All selectors encapsulated; tests must not use element querying.
 * Copy is i18n (verify.*); use regex for heading/button to support default locale.
 */
const { expect } = require('@playwright/test')

/** Heading text (en: "Check your email") */
const HEADING_REGEX = /check your email|revisa tu correo|تحقق من بريدك/i
/** Resend button text (en: "Resend") */
const RESEND_REGEX = /resend|reenviar|إعادة الإرسال/i
/** Back to sign in link (en: "Back to sign in") */
const BACK_TO_SIGN_IN_REGEX = /back to sign in|volver a iniciar|العودة لتسجيل/i
/** Go to sign in button (en: "Go to sign in") */
const GO_TO_SIGN_IN_REGEX = /go to sign in|ir a iniciar|الذهاب لتسجيل/i

class VerifyEmailPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to verify-email, optionally with query.email and query.message */
  async goto(query = {}) {
    const params = new URLSearchParams(query)
    const qs = params.toString()
    await this.page.goto(qs ? `/verify-email?${qs}` : '/verify-email')
  }

  /** Assert the "Check your email" heading is visible (i18n-safe). */
  async expectCheckYourEmailHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: HEADING_REGEX })).toBeVisible()
  }

  /** Assert the resend control (button with "Resend" text) is visible. */
  async expectResendVisible() {
    await expect(this.page.getByRole('button', { name: RESEND_REGEX })).toBeVisible()
  }

  /** Click the resend button (inline text button). */
  async clickResend() {
    await this.page.getByRole('button', { name: RESEND_REGEX }).click()
  }

  /** Assert "Back to sign in" link is visible and navigates to Login. */
  async expectBackToSignInLinkVisible() {
    await expect(this.page.getByRole('link', { name: BACK_TO_SIGN_IN_REGEX })).toBeVisible()
  }

  /** Click "Back to sign in" link. */
  async clickBackToSignIn() {
    await this.page.getByRole('link', { name: BACK_TO_SIGN_IN_REGEX }).click()
  }

  /** Assert "Go to sign in" primary CTA (link) is visible. */
  async expectGoToSignInButtonVisible() {
    await expect(this.page.getByRole('link', { name: GO_TO_SIGN_IN_REGEX })).toBeVisible()
  }

  /** Assert URL is verify-email with optional email query (encoded or decoded in URL). */
  async expectVerifyEmailUrl(emailInQuery = null) {
    await expect(this.page).toHaveURL(/\/verify-email/)
    if (emailInQuery != null) {
      // URL may show email with @ or %40
      const escaped = emailInQuery.replace(/\./g, '\\.').replace('@', '(@|%40)')
      await expect(this.page).toHaveURL(new RegExp(`email=${escaped}`))
    }
  }

  /** Assert displayed email text is visible (e.g. after redirect with query.email). */
  async expectEmailDisplayed(emailSubstring) {
    await expect(this.page.getByText(emailSubstring)).toBeVisible()
  }

  /** Assert resend success or error message area (role status or alert). */
  async expectResendFeedbackVisible() {
    await expect(
      this.page.getByRole('status').or(this.page.getByRole('alert'))
    ).toBeVisible()
  }
}

module.exports = { VerifyEmailPage, HEADING_REGEX, RESEND_REGEX }
