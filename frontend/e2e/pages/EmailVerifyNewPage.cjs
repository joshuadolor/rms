// @ts-check
/**
 * Page object for the Email Verify New page (/email/verify-new).
 * Shows loading, then success ("Email updated") or failure ("Verification failed") card.
 * All selectors encapsulated; copy is i18n (verify.*).
 */
const { expect } = require('@playwright/test')

/** Loading text (en: "Verifying your new email…") */
const VERIFYING_NEW_REGEX = /verifying your new email|verificando tu nuevo correo|جاري التحقق من بريدك الجديد/i
/** Success heading (en: "Email updated") */
const SUCCESS_HEADING_REGEX = /email updated|correo actualizado|تم تحديث البريد/i
/** Failure heading (en: "Verification failed") */
const FAILURE_HEADING_REGEX = /verification failed|verificación fallida|فشل التحقق/i
/** Go to dashboard on success (en: "Go to dashboard") */
const GO_TO_DASHBOARD_REGEX = /go to dashboard|ir al panel|الذهاب إلى لوحة التحكم/i
/** Go to sign in on failure (en: "Go to sign in") */
const GO_TO_SIGN_IN_REGEX = /go to sign in|ir a iniciar|الذهاب لتسجيل/i

class EmailVerifyNewPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /**
   * Navigate to /email/verify-new with signed-link query params.
   * @param {{ uuid: string, hash: string, expires: string, signature: string }} params
   */
  async goto(params) {
    const qs = new URLSearchParams(params).toString()
    await this.page.goto(`/email/verify-new?${qs}`)
  }

  /** Assert loading state is visible. */
  async expectVerifyingVisible() {
    await expect(this.page.getByRole('status', { name: VERIFYING_NEW_REGEX })).toBeVisible()
  }

  /** Assert success card: "Email updated" heading and Go to dashboard button. */
  async expectSuccessCardVisible() {
    await expect(this.page.getByRole('heading', { name: SUCCESS_HEADING_REGEX })).toBeVisible()
    await expect(this.page.getByRole('link', { name: GO_TO_DASHBOARD_REGEX })).toBeVisible()
  }

  /** Assert failure card: "Verification failed" heading and Go to sign in. */
  async expectFailureCardVisible() {
    await expect(this.page.getByRole('heading', { name: FAILURE_HEADING_REGEX })).toBeVisible()
    await expect(this.page.getByRole('link', { name: GO_TO_SIGN_IN_REGEX })).toBeVisible()
  }

  /** Wait for result (success or failure card). */
  async waitForResult(timeoutMs = 10000) {
    await Promise.race([
      this.page.getByRole('heading', { name: SUCCESS_HEADING_REGEX }).waitFor({ state: 'visible', timeout: timeoutMs }),
      this.page.getByRole('heading', { name: FAILURE_HEADING_REGEX }).waitFor({ state: 'visible', timeout: timeoutMs }),
    ])
  }
}

module.exports = { EmailVerifyNewPage, SUCCESS_HEADING_REGEX, FAILURE_HEADING_REGEX }
