// @ts-check
/**
 * Page object for the App layout when logged in: sidenav and superadmin vs owner nav.
 * All selectors and assertions encapsulated.
 * Nav links are scoped to the sidebar (aside Main navigation) to avoid matching dashboard content links.
 */
const { expect } = require('@playwright/test')

class SuperadminAppPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Sidebar container (aside with Main navigation) so nav links don't match dashboard links. */
  _sidebar() {
    return this.page.getByRole('complementary', { name: 'Main navigation' })
  }

  /** Open sidebar on mobile (no-op on desktop). Call before asserting nav links if on mobile. */
  async openSidebarIfMobile() {
    const menuButton = this.page.getByRole('button', { name: /open menu/i })
    if (await menuButton.isVisible()) {
      await menuButton.click()
    }
  }

  async goto(path) {
    await this.page.goto(path)
  }

  /** Assert current URL is /app (or /app/) — used after visiting superadmin route as regular user. */
  async expectRedirectedToApp() {
    await expect(this.page).toHaveURL(/\/app(\/)?$/)
  }

  /** Assert sidenav shows Dashboard link. */
  async expectDashboardLinkVisible() {
    await this.openSidebarIfMobile()
    await expect(this._sidebar().getByRole('link', { name: 'Dashboard' })).toBeVisible()
  }

  /** Assert sidenav shows Users link (superadmin only). */
  async expectUsersLinkVisible() {
    await this.openSidebarIfMobile()
    await expect(this._sidebar().getByRole('link', { name: 'Users' })).toBeVisible()
  }

  /** Assert sidenav shows Restaurants link. */
  async expectRestaurantsLinkVisible() {
    await this.openSidebarIfMobile()
    await expect(this._sidebar().getByRole('link', { name: 'Restaurants' })).toBeVisible()
  }

  /** Assert sidenav shows Profile & Settings link. */
  async expectProfileLinkVisible() {
    await this.openSidebarIfMobile()
    await expect(this._sidebar().getByRole('link', { name: /profile & settings/i })).toBeVisible()
  }

  /** Assert sidenav does NOT show Menu items link (owner nav). */
  async expectMenuItemsLinkNotVisible() {
    await this.openSidebarIfMobile()
    await expect(this.page.getByRole('link', { name: /menu items/i })).not.toBeVisible()
  }

  /** Assert sidenav does NOT show owner Feedbacks link (/app/feedbacks). Superadmin nav has "Owner feedbacks" which also matches /feedbacks/i. */
  async expectFeedbacksLinkNotVisible() {
    await this.openSidebarIfMobile()
    await expect(this.page.locator('a[href="/app/feedbacks"]')).not.toBeVisible()
  }

  /** Navigate to Dashboard via sidenav. */
  async navigateToDashboard() {
    await this.openSidebarIfMobile()
    await this._sidebar().getByRole('link', { name: 'Dashboard' }).click()
  }

  /** Navigate to Users via sidenav and wait for the Users page to load. */
  async navigateToUsers() {
    await this.openSidebarIfMobile()
    await this._sidebar().getByRole('link', { name: 'Users' }).click()
    await this.page.waitForURL(/\/app\/superadmin\/users/, { timeout: 10000 })
  }

  /** Navigate to Restaurants via sidenav and wait for the Restaurants page to load. */
  async navigateToRestaurants() {
    await this.openSidebarIfMobile()
    await this._sidebar().getByRole('link', { name: 'Restaurants' }).click()
    await this.page.waitForURL(/\/app\/superadmin\/restaurants/, { timeout: 10000 })
  }

  /** Navigate to Owner feedbacks via sidenav and wait for the page to load. */
  async navigateToOwnerFeedbacks() {
    await this.openSidebarIfMobile()
    await this._sidebar().getByRole('link', { name: 'Owner feedbacks' }).click()
    await this.page.waitForURL(/\/app\/superadmin\/owner-feedbacks/, { timeout: 10000 })
  }

  /** Navigate to Terms & Privacy (legal) via sidenav and wait for the page to load. */
  async navigateToLegal() {
    await this.openSidebarIfMobile()
    await this._sidebar().getByRole('link', { name: /terms.*privacy|privacy.*terms/i }).click()
    await this.page.waitForURL(/\/app\/superadmin\/legal/, { timeout: 10000 })
  }

  /** Assert URL is /app/superadmin/users. */
  async expectUsersPageUrl() {
    await expect(this.page).toHaveURL(/\/app\/superadmin\/users/, { timeout: 10000 })
  }

  /** Assert URL is /app/superadmin/restaurants. */
  async expectRestaurantsPageUrl() {
    await expect(this.page).toHaveURL(/\/app\/superadmin\/restaurants/, { timeout: 10000 })
  }

  /** Assert URL is /app/superadmin/owner-feedbacks. */
  async expectOwnerFeedbacksPageUrl() {
    await expect(this.page).toHaveURL(/\/app\/superadmin\/owner-feedbacks/, { timeout: 10000 })
  }

  /** Assert URL is /app/superadmin/legal. */
  async expectLegalPageUrl() {
    await expect(this.page).toHaveURL(/\/app\/superadmin\/legal/, { timeout: 10000 })
  }
}

module.exports = { SuperadminAppPage }
