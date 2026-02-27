// @ts-check
/**
 * Page object for the Restaurant form (create page or Profile tab on manage page).
 * Encapsulates form fields, Advanced details section, Year established, and submit.
 * All selectors and element interactions live here; tests use only this API.
 */
const { expect } = require('@playwright/test')

class RestaurantFormPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to the create restaurant page and expect the form to be visible. */
  async goToCreatePage() {
    await this.page.goto('/app/restaurants/new')
    await expect(this.page.getByTestId('restaurant-form')).toBeVisible({ timeout: 10000 })
  }

  /** Assert the create form UI: heading, name field, Create restaurant and Cancel buttons, Basic information section. */
  async expectCreateFormVisible() {
    await expect(this.page.getByRole('heading', { name: 'Add new restaurant' })).toBeVisible()
    await expect(this.page.getByLabel('Restaurant name')).toBeVisible()
    await expect(this.page.getByRole('button', { name: 'Create restaurant' })).toBeVisible()
    await expect(this.page.getByRole('button', { name: 'Cancel' })).toBeVisible()
    await expect(this.page.getByRole('heading', { name: 'Basic information' })).toBeVisible()
  }

  /** Fill the Restaurant name field. */
  async fillName(name) {
    await this.page.getByLabel('Restaurant name').fill(name)
  }

  /** Expand the Advanced details section if present (so Year established is visible). If the form has no toggle, just ensure the year-established input is visible. */
  async expandAdvancedDetails() {
    const toggle = this.page.getByTestId('form-toggle-advanced')
    try {
      await toggle.click({ timeout: 2000 })
    } catch {
      // No Advanced toggle (e.g. create/edit form shows year established inline)
    }
    await expect(this.page.getByTestId('form-input-year-established').locator('input')).toBeVisible({ timeout: 5000 })
  }

  /** Fill the Year established field. Pass a string (e.g. '1995'). */
  async fillYearEstablished(value) {
    await this.page.getByTestId('form-input-year-established').locator('input').fill(value)
  }

  /** Assert the Year established input has the given value. */
  async expectYearEstablishedValue(value) {
    await expect(this.page.getByTestId('form-input-year-established').locator('input')).toHaveValue(value, { timeout: 10000 })
  }

  /** Click Save changes (edit form). */
  async submitSaveChanges() {
    await this.page.getByTestId('form-submit').click()
  }

  /** Click Create restaurant (create form). */
  async submitCreate() {
    await this.page.getByRole('button', { name: 'Create restaurant' }).click()
  }

  /** Assert the Phone field is not visible (create form does not show phone). */
  async expectPhoneFieldHidden() {
    await expect(this.page.getByLabel('Phone (optional)')).not.toBeVisible()
  }

  /** Assert the Availability section is not visible (create form does not show availability). */
  async expectAvailabilitySectionHidden() {
    await expect(this.page.getByRole('heading', { name: 'Availability' })).not.toBeVisible()
  }
}

module.exports = { RestaurantFormPage }
