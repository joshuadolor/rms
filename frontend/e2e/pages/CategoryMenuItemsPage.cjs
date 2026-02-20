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

  /** Assert the gradient empty state is visible: heading "Add menu items to this category" and CTA */
  async expectEmptyState() {
    await expect(this.page.getByRole('heading', { name: 'Add menu items to this category', level: 3 })).toBeVisible()
    await this.expectAddMenuItemButtonVisible()
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

  /** Assert the modal search input is visible (AppInput with label "Search") */
  async expectAddItemModalSearchVisible() {
    await expect(this.page.getByRole('dialog').getByLabel('Search')).toBeVisible()
  }

  /** Assert the Add item modal shows the addable list (search + Done button; no toggle). */
  async expectAddItemModalContentVisible() {
    const dialog = this.page.getByRole('dialog')
    await expect(dialog.getByRole('button', { name: 'Done' })).toBeVisible()
  }

  /** Close the Add item modal via Done button */
  async closeAddItemModal() {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Done' }).click()
  }

  /**
   * In the Add item modal, click Add for the item. Waits for list to load.
   * @param {string} itemDisplayName - Display name (e.g. 'Burger') for aria-label fallback
   * @param {string} [itemUuid] - Optional item uuid for stable data-testid selector
   */
  async addItemToCategoryInModal(itemDisplayName, itemUuid) {
    const dialog = this.page.getByRole('dialog')
    await expect(dialog.getByText('Loading menu items…')).not.toBeVisible({ timeout: 15000 })
    const addButton = itemUuid
      ? dialog.getByTestId(`add-item-to-category-${itemUuid}`)
      : dialog.getByRole('button', { name: `Add ${itemDisplayName} to category` })
    await expect(addButton).toBeVisible({ timeout: 10000 })
    await addButton.click()
  }

  /** Click Edit on the first menu item in the list (aria-label "Edit menu item") */
  async clickEditFirstItem() {
    await this.page.getByRole('link', { name: 'Edit menu item' }).first().click()
  }

  /** Assert an item with the given name is visible in the list */
  async expectItemVisible(itemName) {
    await expect(this.page.getByText(itemName).first()).toBeVisible()
  }

  /** Assert the visibility toggle (Show/Hide on public menu) is visible */
  async expectVisibilityToggleVisible() {
    const toggle = this.page.getByRole('button', { name: /^(Hide|Show) on public menu$/ })
    await expect(toggle.first()).toBeVisible()
  }

  /** Click the first visibility toggle (Hide/Show on public menu) */
  async clickFirstVisibilityToggle() {
    await this.page.getByRole('button', { name: /^(Hide|Show) on public menu$/ }).first().click()
  }

  /** Assert the visibility toggle shows "Hide on public menu" (item is visible on menu) */
  async expectVisibilityToggleLabelHide() {
    await expect(this.page.getByRole('button', { name: 'Hide on public menu' }).first()).toBeVisible()
  }

  /** Assert the visibility toggle shows "Show on public menu" (item is hidden from menu) */
  async expectVisibilityToggleLabelShow() {
    await expect(this.page.getByRole('button', { name: 'Show on public menu' }).first()).toBeVisible()
  }

  /** Assert the availability toggle (Available / Not available) is visible */
  async expectAvailabilityToggleVisible() {
    const toggle = this.page.getByRole('button', { name: /Mark (not )?available on public menu$/ })
    await expect(toggle.first()).toBeVisible()
  }

  /** Click the first availability toggle (Mark not available / Mark available on public menu) */
  async clickFirstAvailabilityToggle() {
    await this.page.getByRole('button', { name: /Mark (not )?available on public menu$/ }).first().click()
  }

  /** Assert the availability toggle shows "Mark not available on public menu" (item is currently available) */
  async expectAvailabilityShowsAvailable() {
    await expect(this.page.getByRole('button', { name: 'Mark not available on public menu' }).first()).toBeVisible()
  }

  /** Assert the availability toggle shows "Mark available on public menu" (item is currently not available) */
  async expectAvailabilityShowsNotAvailable() {
    await expect(this.page.getByRole('button', { name: 'Mark available on public menu' }).first()).toBeVisible()
  }

  /** Open the availability modal for the menu item row that contains the given item name ("Set availability times for this menu item") */
  async openItemAvailabilityModal(itemName) {
    const row = this.page.locator('li').filter({ hasText: itemName })
    await row.getByRole('button', { name: 'Set availability times for this menu item' }).click()
  }

  /**
   * In the Add item modal, assert a variant row with this exact display name is visible
   * (e.g. "Pizza – Hawaiian, Small"). Use after modal is open and list has loaded.
   */
  async expectAddModalVariantRowVisible(variantDisplayName) {
    const dialog = this.page.getByRole('dialog')
    await expect(dialog.getByText(variantDisplayName)).toBeVisible({ timeout: 10000 })
  }

  /**
   * In the Add item modal, assert the base item name is NOT shown as a single addable row.
   * (e.g. "Pizza" alone should not appear; only variant rows like "Pizza – Hawaiian, Small").
   */
  async expectAddModalBaseItemNotVisible(baseNameOnly) {
    const dialog = this.page.getByRole('dialog')
    await expect(dialog.getByText(baseNameOnly, { exact: true })).not.toBeVisible()
  }

  /** Click the floating Help button (opens legend modal) */
  async openHelpLegend() {
    await this.page.getByTestId('help-legend-button').click()
  }

  /** Assert the Help legend modal is open (dialog with heading "Help") */
  async expectHelpModalVisible() {
    const dialog = this.page.getByRole('dialog', { name: 'Help' })
    await expect(dialog).toBeVisible({ timeout: 5000 })
  }

  /** Assert the Help modal body contains the given text (uses first match to avoid strict mode when icon + label share text) */
  async expectHelpLegendContains(text) {
    const dialog = this.page.getByRole('dialog', { name: 'Help' })
    await expect(dialog.getByText(text).first()).toBeVisible()
  }

  /** Close the Help modal (uses header close control to avoid multiple "Close" buttons) */
  async closeHelpModal() {
    await this.page.getByRole('dialog', { name: 'Help' }).getByTestId('app-modal-close').click()
  }
}

module.exports = { CategoryMenuItemsPage }
