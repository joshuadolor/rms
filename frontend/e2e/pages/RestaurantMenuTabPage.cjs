// @ts-check
/**
 * Page object for the Restaurant Menu tab (Menus & categories): menu selector,
 * Add menu button, Add menu modal (create second menu), and category list.
 * All selectors and element interactions are encapsulated here.
 */
const { expect } = require('@playwright/test')

class RestaurantMenuTabPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to restaurant manage page and click the Menu tab */
  async goToMenuTab(restaurantUuid) {
    await this.page.goto(`/app/restaurants/${restaurantUuid}`)
    await this.page.getByRole('tab', { name: 'Menu' }).click()
  }

  /** Assert the header "Add menu" button is visible (shown when at least one menu exists). Uses "add Add menu" to avoid matching FAB. */
  async expectAddMenuButtonVisible() {
    await expect(this.page.getByRole('button', { name: 'add Add menu' })).toBeVisible()
  }

  /** Open the Add menu modal by clicking the header Add menu button (not the FAB) */
  async openAddMenuModal() {
    await this.page.getByRole('button', { name: 'add Add menu' }).click()
  }

  /** Assert the Add menu modal is open (heading visible) */
  async expectAddMenuModalOpen() {
    await expect(this.page.getByRole('heading', { name: 'Add menu' })).toBeVisible()
  }

  /** Fill the optional Menu name field in the Add menu modal */
  async setMenuName(name) {
    await this.page.getByLabel('Menu name').fill(name)
  }

  /** Submit the Add menu form (Create menu button in modal) */
  async submitCreateMenu() {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Create menu' }).click()
  }

  /** Cancel the Add menu modal (Cancel button) */
  async cancelAddMenuModal() {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Cancel' }).click()
  }

  /** Assert the Add menu modal is no longer visible */
  async expectAddMenuModalClosed() {
    await expect(this.page.getByRole('heading', { name: 'Add menu' })).not.toBeVisible()
  }

  /** Assert the menu selector shows the given menu (selected or in options). Uses text content because options can be hidden when not selected. */
  async expectMenuInSelector(menuDisplayName) {
    const selector = this.page.getByRole('combobox', { name: 'Select menu' })
    await expect(selector).toBeVisible()
    await expect(selector).toContainText(menuDisplayName)
  }

  /** Assert the Menus & categories heading is visible (tab loaded) */
  async expectMenusHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: /Menus & categories/i })).toBeVisible()
  }

  /** Assert the Categories heading is visible (h3, not "Menus & categories" h2) */
  async expectCategoriesHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Categories', level: 3 })).toBeVisible()
  }

  /** Assert "Add menu item" link and button are absent (only "Manage menu items" and per-category "Manage items" exist on this page) */
  async expectAddMenuItemAbsent() {
    await expect(this.page.getByRole('link', { name: 'Add menu item' })).toHaveCount(0)
    await expect(this.page.getByRole('button', { name: 'Add menu item' })).toHaveCount(0)
  }

  /** Open the FAB speed-dial by clicking the main FAB (aria-label "Add menu or category") */
  async openFAB() {
    await this.page.getByRole('button', { name: 'Add menu or category' }).click()
  }

  /** Click "Add menu" in the FAB speed-dial (closes speed-dial and opens Add menu modal) */
  async clickFABAddMenu() {
    await this._fabGroup().getByRole('button', { name: 'Add menu', exact: true }).click()
  }

  /** Click "Add category" in the FAB speed-dial (closes speed-dial and opens Add/Edit category modal) */
  async clickFABAddCategory() {
    await this._fabGroup().getByRole('button', { name: 'Add category', exact: true }).click()
  }

  /** FAB group locator (speed-dial container); use to scope FAB-only actions and avoid header "Add menu" */
  _fabGroup() {
    return this.page.getByRole('group', { name: 'Add menu or category' })
  }

  /** Assert the FAB speed-dial is open: both Add menu and Add category actions are visible (scoped to FAB group; exact to avoid matching "Close add menu") */
  async expectFABSpeedDialVisible() {
    await expect(this._fabGroup().getByRole('button', { name: 'Add menu', exact: true })).toBeVisible()
    await expect(this._fabGroup().getByRole('button', { name: 'Add category', exact: true })).toBeVisible()
  }

  /** Assert the FAB speed-dial is closed (speed-dial Add menu button not present in FAB group) */
  async expectFABSpeedDialClosed() {
    await expect(this._fabGroup().getByRole('button', { name: 'Add menu', exact: true })).toHaveCount(0)
  }

  /** Close the FAB speed-dial by clicking the backdrop */
  async closeFABWithBackdrop() {
    await this.page.getByTestId('fab-speed-dial-backdrop').click()
  }

  /** Close the FAB speed-dial by clicking the FAB button again (aria-label "Close add menu" when open) */
  async closeFABWithButton() {
    await this.page.getByRole('button', { name: 'Close add menu' }).click()
  }

  /** Open the Add category modal (opens FAB then clicks Add category in speed-dial) */
  async openAddCategoryModal() {
    await this.openFAB()
    await this.clickFABAddCategory()
  }

  /** Assert Add category modal is open */
  async expectAddCategoryModalOpen() {
    await expect(this.page.getByRole('heading', { name: 'Add category' })).toBeVisible()
  }

  /** Assert Edit category modal is open */
  async expectEditCategoryModalOpen() {
    await expect(this.page.getByRole('heading', { name: 'Edit category' })).toBeVisible()
  }

  /** Fill the Category name field in the category modal */
  async setCategoryName(name) {
    await this.page.getByLabel('Category name').fill(name)
  }

  /** Submit Save in the category modal (Add or Edit) */
  async submitSaveCategory() {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Save' }).click()
  }

  /** Assert the category modal is closed */
  async expectCategoryModalClosed() {
    await expect(this.page.getByRole('heading', { name: /Add category|Edit category/ })).not.toBeVisible()
  }

  /** Assert a category with the given name is visible in the list */
  async expectCategoryVisible(categoryName) {
    await expect(this.page.getByText(categoryName).first()).toBeVisible()
  }

  /** Click "Manage items" for the category row that contains the given name (link uses aria-label "Manage menu items in this category") */
  async clickManageItemsForCategory(categoryName) {
    const row = this.page.locator('li').filter({ hasText: categoryName })
    await row.getByRole('link', { name: 'Manage menu items in this category' }).click()
  }

  /** Toggle menu active (visibility) for the selected menu; button label is "Hide X on public site" or "Show X on public site" */
  async toggleMenuActive(menuDisplayName) {
    const escaped = menuDisplayName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    await this.page.getByRole('button', { name: new RegExp('(Hide|Show) ' + escaped + ' on public site', 'i') }).click()
  }

  /** Toggle category active (visibility) for the category row containing the given name */
  async toggleCategoryActive(categoryName) {
    const row = this.page.locator('li').filter({ hasText: categoryName })
    await row.getByRole('button', { name: /Hide category|Show category on public menu/i }).click()
  }

  /** Assert the "No categories yet" empty state is visible */
  async expectNoCategoriesMessage() {
    await expect(this.page.getByText('No categories yet. Use the + button below to add one.')).toBeVisible()
  }

  /** Open Edit category modal by clicking edit on the category row with the given name (edit is 3rd button: drag, visibility, edit, delete) */
  async openEditCategoryModal(categoryName) {
    const row = this.page.locator('li').filter({ hasText: categoryName })
    await row.locator('button').nth(2).click()
  }

  /** Open Remove category modal by clicking delete on the category row with the given name */
  async openDeleteCategoryModal(categoryName) {
    const row = this.page.locator('li').filter({ hasText: categoryName })
    await row.getByRole('button', { name: 'Remove category' }).click()
  }

  /** Assert Remove category confirmation dialog is open */
  async expectRemoveCategoryModalOpen() {
    await expect(this.page.getByRole('heading', { name: 'Remove category' })).toBeVisible()
  }

  /** Confirm Remove category in the dialog */
  async confirmRemoveCategory() {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Remove category' }).click()
  }

  /** Cancel the Remove category dialog */
  async cancelRemoveCategoryModal() {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Cancel' }).click()
  }

  /** Click the Rename menu button (next to menu selector when a menu is selected) */
  async clickRenameMenu() {
    await this.page.getByRole('button', { name: 'Rename menu' }).click()
  }

  /** Assert the Rename menu modal is open */
  async expectRenameMenuModalOpen() {
    await expect(this.page.getByRole('heading', { name: 'Rename menu' })).toBeVisible()
  }

  /** Assert the Rename menu modal is closed */
  async expectRenameMenuModalClosed() {
    await expect(this.page.getByRole('heading', { name: 'Rename menu' })).not.toBeVisible()
  }

  /** Set the Menu name in the Rename menu modal (same label as Add menu; scope by open dialog) */
  async setRenameMenuName(name) {
    await this.page.getByRole('dialog', { name: 'Rename menu' }).getByLabel('Menu name').fill(name)
  }

  /** Submit Save in the Rename menu modal */
  async submitRenameMenu() {
    await this.page.getByRole('dialog', { name: 'Rename menu' }).getByRole('button', { name: 'Save' }).click()
  }
}

module.exports = { RestaurantMenuTabPage }
