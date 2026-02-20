// @ts-check
/**
 * Page object for the Restaurant menu item form (Add / Edit menu item) and catalog standalone form.
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

  /** Navigate to catalog Add menu item (/app/menu-items/new) */
  async goToCatalogNew() {
    await this.page.goto('/app/menu-items/new')
  }

  /** Navigate to catalog Edit menu item (/app/menu-items/:itemUuid/edit) */
  async goToCatalogEdit(itemUuid) {
    await this.page.goto(`/app/menu-items/${itemUuid}/edit`)
  }

  /** Assert the Add menu item heading is visible (allow time for restaurant/form load) */
  async expectAddMenuItemHeading() {
    await expect(this.page.getByRole('heading', { name: 'Add menu item' })).toBeVisible({ timeout: 10000 })
  }

  /** Assert the Edit menu item heading is visible */
  async expectEditMenuItemHeading() {
    await expect(this.page.getByRole('heading', { name: 'Edit menu item' })).toBeVisible()
  }

  /** Fill the Name field (catalog form uses "Name"; restaurant form may use "Name (English)") */
  async setName(name) {
    const nameField = this.page.getByLabel(/^Name/).first()
    await nameField.fill(name)
  }

  /** Fill the Name field for the default locale (e.g. "Name (English)") */
  async setNameEn(name) {
    const label = this.page.getByLabel(/Name \(.*\)/).first()
    if (await label.count() > 0) {
      await label.fill(name)
    } else {
      await this.page.getByLabel(/^Name/).first().fill(name)
    }
  }

  /** Fill the Price (optional) field */
  async setPrice(price) {
    await this.page.getByLabel('Price (optional)').fill(String(price))
  }

  /** Select type: Simple */
  async selectTypeSimple() {
    await this.page.getByTestId('type-simple').check()
  }

  /** Select type: Combo */
  async selectTypeCombo() {
    await this.page.getByTestId('type-combo').check()
  }

  /** Select type: With variants */
  async selectTypeWithVariants() {
    await this.page.getByTestId('type-with_variants').check()
  }

  /** Fill Combo price (optional) - only visible when type is combo */
  async setComboPrice(price) {
    await this.page.getByLabel('Combo price (optional)').fill(String(price))
  }

  /** Click Add entry in the Combo entries section */
  async clickAddComboEntry() {
    await this.page.getByRole('button', { name: 'Add entry' }).click()
  }

  /** Select menu item for combo entry by option label (entry 1-based in UI) */
  async setComboEntryItem(entryOneBasedIndex, optionLabel) {
    const entrySection = this.page.getByText(`Entry ${entryOneBasedIndex}`, { exact: false }).locator('..').locator('..')
    await entrySection.getByRole('combobox', { name: /Select menu item|Menu item/i }).selectOption({ label: optionLabel })
  }

  /** Fill quantity for combo entry (entry 1-based) */
  async setComboEntryQuantity(entryOneBasedIndex, value) {
    const entrySection = this.page.getByText(`Entry ${entryOneBasedIndex}`, { exact: false }).locator('..').locator('..')
    await entrySection.getByLabel('Quantity').fill(String(value))
  }

  /** Click Add group in Option groups section */
  async clickAddOptionGroup() {
    await this.page.getByRole('button', { name: 'Add group' }).click()
  }

  /** Fill option group name (group 0-based; first group has label containing "Size", etc.) */
  async setOptionGroupName(groupIndex, name) {
    const groups = this.page.locator('label').filter({ has: this.page.locator('input[type="text"]') }).filter({ hasText: /Group name|Size|Type/ })
    const input = groups.nth(groupIndex).locator('input[type="text"]')
    await input.fill(name)
  }

  /** Fill option group values (one per line or comma-separated). Target by group index via the textarea in option groups. */
  async setOptionGroupValues(groupIndex, text) {
    const section = this.page.getByText('Values (one per line or comma-separated)').locator('..')
    const textareas = section.locator('textarea')
    await textareas.nth(groupIndex).fill(text)
  }

  /** Fill variant SKU price by table row index (0-based) */
  async setVariantSkuPriceByRowIndex(rowIndex, price) {
    const tbody = this.page.getByRole('table').locator('tbody')
    const row = tbody.locator('tr').nth(rowIndex)
    await row.getByRole('spinbutton').fill(String(price))
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

  /** Assert a validation or field error message is visible */
  async expectValidationMessageVisible(message) {
    await expect(this.page.getByText(message)).toBeVisible()
  }

  /** Assert form error alert is visible (role="alert") */
  async expectFormErrorVisible() {
    await expect(this.page.getByRole('alert')).toBeVisible()
  }
}

module.exports = { MenuItemFormPage }
