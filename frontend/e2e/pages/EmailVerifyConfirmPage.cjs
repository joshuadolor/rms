// @ts-check
/**
 * Page object for the Email Verify Confirm page (/email/verify).
 * Shows loading, then success ("Email verified") or failure ("Verification failed") card.
 * All selectors encapsulated; copy is i18n (verify.*).
 */
const { expect } = require('@playwright/test')

/** Loading text (en: "Verifying your email…") */
const VERIFYING_REGEX = /verifying your email|verificando tu correo|جاري التحقق من بريدك/i
/** Success heading (en: "Email verified") */
const SUCCESS_HEADING_REGEX = /email verified|correo verificado|تم التحقق من البريد/i
/** Failure heading (en: "Verification failed") */
const FAILURE_HEADING_REGEX = /verification failed|verificación fallida|فشل التحقق/i
/** Sign in button on success (en: "Sign in") */
const SIGN_IN_REGEX = /sign in|iniciar sesión|تسجيل الدخول/i
/** Go to sign in on failure (en: "Go to sign in") */
const GO_TO_SIGN_IN_REGEX = /go to sign in|ir a iniciar|الذهاب لتسجيل/i

class EmailVerifyConfirmPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /**
   * Navigate to /email/verify with signed-link query params.
   * @param {{ uuid: string, hash: string, expires: string, signature: string }} params
   */
  async goto(params) {
    const qs = new URLSearchParams(params).toString()
    await this.page.goto(`/email/verify?${qs}`)
  }

  /** Assert loading state (verifying message) is visible. */
  async expectVerifyingVisible() {
    await expect(this.page.getByRole('status', { name: VERIFYING_REGEX })).toBeVisible()
  }

  /** Assert success card: "Email verified" heading and optional Sign in button. */
  async expectSuccessCardVisible() {
    await expect(this.page.getByRole('heading', { name: SUCCESS_HEADING_REGEX })).toBeVisible()
    await expect(this.page.getByRole('link', { name: SIGN_IN_REGEX })).toBeVisible()
  }

  /** Assert failure card: "Verification failed" heading and Go to sign in. */
  async expectFailureCardVisible() {
    await expect(this.page.getByRole('heading', { name: FAILURE_HEADING_REGEX })).toBeVisible()
    await expect(this.page.getByRole('link', { name: GO_TO_SIGN_IN_REGEX })).toBeVisible()
  }

  /** Wait for loading to finish (either success or failure card appears). */
  async waitForResult(timeoutMs = 10000) {
    await Promise.race([
      this.page.getByRole('heading', { name: SUCCESS_HEADING_REGEX }).waitFor({ state: 'visible', timeout: timeoutMs }),
      this.page.getByRole('heading', { name: FAILURE_HEADING_REGEX }).waitFor({ state: 'visible', timeout: timeoutMs }),
    ])
  }
}

module.exports = { EmailVerifyConfirmPage, SUCCESS_HEADING_REGEX, FAILURE_HEADING_REGEX }
