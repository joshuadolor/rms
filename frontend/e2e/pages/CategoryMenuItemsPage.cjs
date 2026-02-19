// @ts-check
/**
 * Page object for the Category menu items page (restaurant > menu > category > items).
 * All selectors and element interactions are encapsulated here.
 */
const { expect } = require('@playwright/test')

class CategoryMenuItemsPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to the category items page. Optional query: { name } for breadcrumb/heading. */
  async goTo(restaurantUuid, categoryUuid, query = {}) {
    const q = new URLSearchParams()
    if (query.name) q.set('name', query.name)
    const queryString = q.toString() ? `?${q.toString()}` : ''
    await this.page.goto(`/app/restaurants/${restaurantUuid}/categories/${categoryUuid}/items${queryString}`)
  }

  /** Assert the page heading shows the category name (or "Menu items" if no name in query) */
  async expectCategoryHeading(categoryName) {
    await expect(this.page.getByRole('heading', { name: categoryName, level: 2 })).toBeVisible()
  }

  /** Assert the "Add menu item" button is visible (opens modal on this page) */
  async expectAddMenuItemButtonVisible() {
    await expect(this.page.getByRole('button', { name: 'Add menu item' }).first()).toBeVisible()
  }

  /** Assert the empty state message is visible */
  async expectEmptyState() {
    await expect(this.page.getByText('No menu items in this category yet.')).toBeVisible()
  }

  /** Assert the item count text (e.g. "2 item(s)") */
  async expectItemCount(count) {
    await expect(this.page.getByText(`${count} item(s)`)).toBeVisible()
  }

  /** Click the "Add menu item" button (opens modal) */
  async clickAddMenuItemButton() {
    await this.page.getByRole('button', { name: 'Add menu item' }).first().click()
  }

  /** Assert the Add menu item to category modal is open (heading visible) */
  async expectAddItemModalOpen() {
    await expect(this.page.getByRole('heading', { name: 'Add menu item to category' })).toBeVisible()
  }

  /** Assert the Add item modal is closed */
  async expectAddItemModalClosed() {
    await expect(this.page.getByRole('heading', { name: 'Add menu item to category' })).not.toBeVisible()
  }

  /** Assert the modal search input is visible (input type="search" has role searchbox) */
  async expectAddItemModalSearchVisible() {
    await expect(this.page.getByRole('searchbox', { name: 'Filter menu items by name' })).toBeVisible()
  }

  /** Assert the modal toggle "Not in this category" / "In this category" is visible (exact to avoid substring match) */
  async expectAddItemModalToggleVisible() {
    const dialog = this.page.getByRole('dialog')
    await expect(dialog.getByRole('button', { name: 'Not in this category', exact: true })).toBeVisible()
    await expect(dialog.getByRole('button', { name: 'In this category', exact: true })).toBeVisible()
  }

  /** Close the Add item modal via Done button */
  async closeAddItemModal() {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Done' }).click()
  }

  /** In the Add item modal, click Add for the item with the given display name (must be in "Not in this category" list) */
  async addItemToCategoryInModal(itemDisplayName) {
    const dialog = this.page.getByRole('dialog')
    await dialog.getByRole('button', { name: `Add ${itemDisplayName} to category` }).click()
  }

  /** Click Edit on the first menu item in the list (aria-label "Edit menu item") */
  async clickEditFirstItem() {
    await this.page.getByRole('link', { name: 'Edit menu item' }).first().click()
  }

  /** Assert an item with the given name is visible in the list */
  async expectItemVisible(itemName) {
    await expect(this.page.getByText(itemName).first()).toBeVisible()
  }
}

module.exports = { CategoryMenuItemsPage }
