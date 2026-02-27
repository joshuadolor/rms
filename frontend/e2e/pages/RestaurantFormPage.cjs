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

  /** Fill the Restaurant name field. */
  async fillName(name) {
    await this.page.getByLabel('Restaurant name').fill(name)
  }

  /** Expand the Advanced details section (so Year established and other fields are visible). */
  async expandAdvancedDetails() {
    await this.page.getByTestId('form-toggle-advanced').click()
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
}

module.exports = { RestaurantFormPage }
