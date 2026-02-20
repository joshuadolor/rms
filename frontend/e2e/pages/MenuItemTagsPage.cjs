// @ts-check
/**
 * Page object for the Menu item tags view (/app/menu-item-tags).
 * Read-only list of default tags; no create/edit/delete.
 * All selectors and element interactions are encapsulated here.
 */
const { expect } = require('@playwright/test')

class MenuItemTagsPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to the Menu item tags page */
  async goTo() {
    await this.page.goto('/app/menu-item-tags')
  }

  /** Open Menu item tags from the side nav (link "Menu item tags") */
  async openFromSideNav() {
    await this.page.getByRole('link', { name: 'Menu item tags' }).click()
  }

  /** Assert the page heading "Menu item tags" is visible */
  async expectHeadingVisible() {
    await expect(this.page.getByRole('heading', { name: 'Menu item tags' })).toBeVisible()
  }

  /** Assert breadcrumb shows "Menu items" then "Menu item tags" */
  async expectBreadcrumbMenuItemsThenTags() {
    const nav = this.page.getByRole('navigation', { name: 'Breadcrumb' })
    await expect(nav).toBeVisible()
    await expect(nav.getByRole('link', { name: 'Menu items' })).toBeVisible()
    await expect(nav).toContainText('Menu item tags')
  }

  /** Assert the tags list is visible (main content: the ul of tag rows, not the breadcrumb ol). */
  async expectTagsListVisible() {
    const main = this.page.getByRole('main')
    const tagsList = main.locator('ul')
    const noTagsMessage = main.getByText(/No tags available\.?/)
    await expect(tagsList.or(noTagsMessage)).toBeVisible({ timeout: 10000 })
  }

  /** Assert a tag with the given text is visible in the list */
  async expectTagWithText(text) {
    await expect(this.page.getByText(text, { exact: true }).first()).toBeVisible()
  }
}

module.exports = { MenuItemTagsPage }
