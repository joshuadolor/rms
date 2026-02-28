// @ts-check
/**
 * Page object for the Superadmin Terms & Privacy (legal) page.
 * All selectors and interactions encapsulated; tests must not use element querying.
 */
const { expect } = require('@playwright/test')

const LEGAL_LOCALES = ['en', 'es', 'ar']

class SuperadminLegalPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Assert the legal page container is visible. */
  async expectPageVisible() {
    await expect(this.page.getByTestId('superadmin-legal-page')).toBeVisible()
  }

  /** Assert the page heading (Terms & Privacy) is visible. */
  async expectHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: /terms.*privacy|privacy.*terms/i })).toBeVisible()
  }

  /** Assert locale tabs (English, Spanish, Arabic) are present. */
  async expectTabsVisible() {
    const tablist = this.page.getByRole('tablist', { name: /language/i })
    await expect(tablist).toBeVisible()
    for (const loc of LEGAL_LOCALES) {
      await expect(this.page.locator(`#tab-${loc}`)).toBeVisible()
    }
  }

  /** Switch to the tab for the given locale (en, es, ar). */
  async switchTab(locale) {
    await this.page.locator(`#tab-${locale}`).click()
  }

  /** Fill the Terms of Service textarea for the given locale panel (call switchTab first). */
  async fillTerms(text, locale = 'en') {
    await this.page.locator(`#panel-${locale} [data-testid="legal-terms-input"]`).fill(text)
  }

  /** Fill the Privacy Policy textarea for the given locale panel (call switchTab first). */
  async fillPrivacy(text, locale = 'en') {
    await this.page.locator(`#panel-${locale} [data-testid="legal-privacy-input"]`).fill(text)
  }

  /** Click the Save changes button. */
  async save() {
    await this.page.getByRole('button', { name: /save changes|guardar|حفظ/i }).click()
  }

  /** Assert no save error alert is visible. */
  async expectNoSaveError() {
    const alert = this.page.getByRole('alert').filter({ hasText: /failed|error|falió|فشل/i })
    await expect(alert).not.toBeVisible()
  }

  /** Assert a success toast or message is shown (e.g. "Legal content updated"). */
  async expectSaveSuccess() {
    await expect(this.page.getByText(/legal content updated|contenido legal actualizado|تم تحديث المحتوى القانوني/i)).toBeVisible({ timeout: 5000 })
  }
}

module.exports = { SuperadminLegalPage, LEGAL_LOCALES }
