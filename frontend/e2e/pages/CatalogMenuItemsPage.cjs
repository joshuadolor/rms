// @ts-check
/**
 * Page object for the catalog Menu items list (/app/menu-items).
 * All selectors and element interactions are encapsulated here.
 */
const { expect } = require('@playwright/test')

class CatalogMenuItemsPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to the catalog menu items list */
  async goToList() {
    await this.page.goto('/app/menu-items')
  }

  /** Assert the Menu items list heading is visible */
  async expectMenuItemsHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Menu items' })).toBeVisible({ timeout: 10000 })
  }

  /** Assert empty state (no items) is shown */
  async expectEmptyState() {
    await expect(this.page.getByRole('heading', { name: 'Add your first menu item' })).toBeVisible()
    await expect(this.page.getByRole('link', { name: 'Add menu item' })).toBeVisible()
  }

  /** Assert an item with the given name is visible in the list */
  async expectItemVisible(itemName) {
    await expect(this.page.getByTestId(/^menu-item-row-/).filter({ hasText: itemName })).toBeVisible()
  }

  /** Assert the row for the given item uuid shows the given type badge (Simple, Combo, With variants) */
  async expectItemTypeBadge(itemUuid, typeLabel) {
    const row = this.page.getByTestId(`menu-item-row-${itemUuid}`)
    await expect(row.getByText(typeLabel, { exact: true })).toBeVisible()
  }

  /** Click the floating Add menu item button (when list has items) */
  async clickAddMenuItemFab() {
    await this.page.getByRole('link', { name: 'Add menu item' }).click()
  }

  /** Click Add menu item from empty state */
  async clickAddMenuItemFromEmptyState() {
    await this.page.getByRole('link', { name: 'Add menu item' }).click()
  }

  /** Click the Edit button for the item with the given uuid */
  async clickEditMenuItem(itemUuid) {
    const row = this.page.getByTestId(`menu-item-row-${itemUuid}`)
    await row.getByRole('link', { name: 'Edit menu item' }).click()
  }

  /** Assert the list has exactly n items */
  async expectItemCount(n) {
    await expect(this.page.getByTestId(/^menu-item-row-/)).toHaveCount(n)
  }
}

module.exports = { CatalogMenuItemsPage }
