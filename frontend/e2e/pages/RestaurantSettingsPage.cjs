// @ts-check
/**
 * Page object for the Restaurant Settings tab: currency, languages list,
 * description-by-language (single dropdown + one textarea, Save, Translate from default),
 * and Show all languages / Show less when more than 5 languages.
 * All selectors and element interactions are encapsulated here.
 */
const { expect } = require('@playwright/test')

class RestaurantSettingsPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to restaurant manage page and click the Settings tab */
  async goToSettingsTab(restaurantUuid) {
    await this.page.goto(`/app/restaurants/${restaurantUuid}`)
    await this.page.getByRole('tab', { name: 'Settings' }).click()
  }

  /** Assert the Settings heading is visible */
  async expectSettingsHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Settings' })).toBeVisible()
  }

  /** Assert the Currency combobox is visible */
  async expectCurrencySelectVisible() {
    await expect(this.page.getByRole('combobox', { name: /currency/i })).toBeVisible()
  }

  /** Assert the Languages section heading is visible */
  async expectLanguagesHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Languages' })).toBeVisible()
  }

  /** Assert the description-by-language section is visible: locale dropdown, Save button, and textarea for the given locale */
  async expectDescriptionSectionVisible() {
    await expect(this.page.getByTestId('settings-description-locale-select')).toBeVisible()
    await expect(this.page.getByTestId('settings-description-save')).toBeVisible()
  }

  /** Assert the description textarea for the given locale is visible (testid is settings-description-{locale}) */
  async expectDescriptionTextareaVisibleForLocale(locale) {
    await expect(this.page.getByTestId(`settings-description-${locale}`)).toBeVisible()
  }

  /** Select a locale in the "Edit description for" dropdown by value (e.g. 'en', 'fr') */
  async selectDescriptionLocale(locale) {
    await this.page.getByTestId('settings-description-locale-select').selectOption(locale)
  }

  /** Fill the description textarea for the given locale (textarea testid is settings-description-{locale}) */
  async fillDescriptionForLocale(locale, text) {
    await this.page.getByTestId(`settings-description-${locale}`).fill(text)
  }

  /** Click the Save button for the description */
  async clickSaveDescription() {
    await this.page.getByTestId('settings-description-save').click()
  }

  /** Assert the "Translate from default" button is visible */
  async expectTranslateFromDefaultVisible() {
    await expect(this.page.getByTestId('settings-translate-from-default')).toBeVisible()
  }

  /** Click "Translate from default" */
  async clickTranslateFromDefault() {
    await this.page.getByTestId('settings-translate-from-default').click()
  }

  /** Assert the "Show all languages (N total)" button is visible */
  async expectShowAllLanguagesVisible() {
    await expect(this.page.getByTestId('settings-show-all-languages')).toBeVisible()
  }

  /** Click "Show all languages (N total)" to expand the list */
  async clickShowAllLanguages() {
    await this.page.getByTestId('settings-show-all-languages').click()
  }

  /** Assert the "Show less" button is visible */
  async expectShowLessLanguagesVisible() {
    await expect(this.page.getByTestId('settings-show-less-languages')).toBeVisible()
  }

  /** Click "Show less" to collapse the language list */
  async clickShowLessLanguages() {
    await this.page.getByTestId('settings-show-less-languages').click()
  }

  /** Assert the "Show all languages" button shows the given total count (e.g. 6) */
  async expectShowAllLanguagesCount(totalCount) {
    await expect(this.page.getByTestId('settings-show-all-languages')).toContainText(`${totalCount} total`)
  }

  /** Assert the description textarea for the given locale has the expected value */
  async expectDescriptionValueForLocale(locale, value) {
    await expect(this.page.getByTestId(`settings-description-${locale}`)).toHaveValue(value)
  }

  /** Click Remove for a non-default language (by display name, e.g. "French") to open the remove-language confirmation modal */
  async clickRemoveLanguage(displayName) {
    await this.page.getByRole('listitem').filter({ hasText: displayName }).getByRole('button', { name: 'Remove', exact: true }).click()
  }

  /** Assert the Remove language confirmation modal is open */
  async expectRemoveLanguageModalOpen() {
    await expect(this.page.getByTestId('settings-remove-language-modal')).toBeVisible()
    await expect(this.page.getByRole('heading', { name: 'Remove language?' })).toBeVisible()
  }

  /** Confirm Remove in the remove-language modal */
  async confirmRemoveLanguageModal() {
    await this.page.getByTestId('settings-remove-language-confirm').click()
  }

  /** Cancel the remove-language modal */
  async cancelRemoveLanguageModal() {
    await this.page.getByTestId('settings-remove-language-cancel').click()
  }

  /** Assert the remove-language modal is closed */
  async expectRemoveLanguageModalClosed() {
    await expect(this.page.getByTestId('settings-remove-language-modal')).not.toBeVisible()
  }

  /** Assert a language row (by display name, e.g. "English" or "French") is visible in the Languages list */
  async expectLanguageRowVisible(displayText) {
    await expect(this.page.getByRole('listitem').filter({ hasText: displayText })).toBeVisible()
  }

  /** Assert a language row with the given display name is not visible (e.g. after removal) */
  async expectLanguageRowNotVisible(displayText) {
    await expect(this.page.getByRole('listitem').filter({ hasText: displayText })).not.toBeVisible()
  }

  /** Add a language via the Add language dropdown and Add button (select value e.g. "fr" for French) */
  async addLanguage(localeValue) {
    await this.page.getByTestId('settings-add-language-select').selectOption(localeValue)
    await this.page.getByTestId('settings-add-language-button').click()
  }

  // --- Public page template section ---

  /** Assert the "Public page template" section is visible. */
  async expectTemplateSectionVisible() {
    await expect(this.page.getByTestId('settings-section-template')).toBeVisible()
    await expect(this.page.getByRole('heading', { name: 'Public page template' })).toBeVisible()
  }

  /** Select a template by id (e.g. 'default', 'minimal') by clicking its card. */
  async selectTemplate(templateId) {
    await this.page.getByTestId(`settings-template-${templateId}`).click()
  }

  /** Assert the template card with the given id is in selected state (aria-pressed="true"). */
  async expectTemplateCardSelected(templateId) {
    await expect(this.page.getByTestId(`settings-template-${templateId}`)).toHaveAttribute('aria-pressed', 'true')
  }

  /** Assert the template card with the given id is not selected. */
  async expectTemplateCardNotSelected(templateId) {
    await expect(this.page.getByTestId(`settings-template-${templateId}`)).toHaveAttribute('aria-pressed', 'false')
  }

  /** Assert no template error message is visible in the template section. */
  async expectNoTemplateError() {
    const section = this.page.getByTestId('settings-section-template')
    await expect(section.getByText('Please choose a valid template.')).not.toBeVisible()
  }
}

module.exports = { RestaurantSettingsPage }
