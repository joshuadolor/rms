// @ts-check
/**
 * Page object for the Restaurant menu item form (Add / Edit menu item).
 * All selectors and element interactions are encapsulated here.
 */
const { expect } = require('@playwright/test')

class MenuItemFormPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to Add menu item for a restaurant. Optional query: category_uuid, return, name */
  async goToNew(restaurantUuid, query = {}) {
    const q = new URLSearchParams(query)
    const queryString = q.toString() ? `?${q.toString()}` : ''
    await this.page.goto(`/app/restaurants/${restaurantUuid}/menu-items/new${queryString}`)
  }

  /** Navigate to Edit menu item for a restaurant */
  async goToEdit(restaurantUuid, itemUuid) {
    await this.page.goto(`/app/restaurants/${restaurantUuid}/menu-items/${itemUuid}/edit`)
  }

  /** Assert the Add menu item heading is visible (allow time for restaurant/form load) */
  async expectAddMenuItemHeading() {
    await expect(this.page.getByRole('heading', { name: 'Add menu item' })).toBeVisible({ timeout: 10000 })
  }

  /** Assert the Edit menu item heading is visible */
  async expectEditMenuItemHeading() {
    await expect(this.page.getByRole('heading', { name: 'Edit menu item' })).toBeVisible()
  }

  /** Fill the Name field for the default locale (e.g. "Name (English)") */
  async setNameEn(name) {
    await this.page.getByLabel(/Name \(.*\)/).first().fill(name)
  }

  /** Fill the Price (optional) field */
  async setPrice(price) {
    await this.page.getByLabel('Price (optional)').fill(String(price))
  }

  /** Click Create item (submit on create form) */
  async submitCreateItem() {
    await this.page.getByRole('button', { name: 'Create item' }).click()
  }

  /** Click Save changes (submit on edit form) */
  async submitSaveChanges() {
    await this.page.getByRole('button', { name: 'Save changes' }).click()
  }

  /** Assert the Revert to base value button is visible */
  async expectRevertToBaseVisible() {
    await expect(this.page.getByRole('button', { name: 'Revert to base value' })).toBeVisible()
  }

  /** Click Revert to base value */
  async clickRevertToBase() {
    await this.page.getByRole('button', { name: 'Revert to base value' }).click()
  }
}

module.exports = { MenuItemFormPage }
