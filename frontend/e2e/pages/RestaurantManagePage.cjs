// @ts-check
/**
 * Page object for Restaurant Manage page: banner/logo block and Logo & banner modal.
 * All selectors and element interactions are encapsulated here.
 * Used for E2E tests that assert opening the image modal, uploading logo/banner,
 * and seeing the logo appear on the manage page without full refresh.
 */
const { expect } = require('@playwright/test')

/** Minimal valid 1x1 PNG for file upload (passes accept and size checks). */
const MINIMAL_PNG_BUFFER = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64'
)

class RestaurantManagePage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to restaurant manage page. */
  async goToManagePage(restaurantUuid) {
    await this.page.goto(`/app/restaurants/${restaurantUuid}`)
    await expect(this.page.getByTestId('restaurant-manage-page')).toBeVisible({ timeout: 10000 })
  }

  /** Open the Logo & banner modal by clicking the banner/logo block. */
  async openLogoBannerModal() {
    await this.page.getByTestId('manage-banner-button').click()
  }

  /** Assert the Logo & banner modal content is visible. */
  async expectLogoBannerModalOpen() {
    await expect(this.page.getByTestId('manage-image-modal-content')).toBeVisible()
    await expect(this.page.getByTestId('manage-image-modal-logo')).toBeVisible()
  }

  /** Assert the Logo & banner modal is closed (content not visible). */
  async expectLogoBannerModalClosed() {
    await expect(this.page.getByTestId('manage-image-modal-content')).not.toBeVisible()
  }

  /**
   * Set a file on the logo file input (triggers upload flow). Uses a minimal PNG if no path given.
   * @param {{ path?: string, buffer?: Buffer, name?: string }} [options] - path to file, or buffer + name for virtual file
   */
  async setLogoFile(options = {}) {
    const input = this.page.getByTestId('manage-image-logo-input')
    if (options.path) {
      await input.setInputFiles(options.path)
    } else {
      await input.setInputFiles({
        name: options.name || 'logo.png',
        mimeType: 'image/png',
        buffer: options.buffer || MINIMAL_PNG_BUFFER,
      })
    }
  }

  /**
   * Set a file on the banner file input. Uses minimal PNG if no path given.
   * @param {{ path?: string, buffer?: Buffer, name?: string }} [options]
   */
  async setBannerFile(options = {}) {
    const input = this.page.getByTestId('manage-image-banner-input')
    if (options.path) {
      await input.setInputFiles(options.path)
    } else {
      await input.setInputFiles({
        name: options.name || 'banner.png',
        mimeType: 'image/png',
        buffer: options.buffer || MINIMAL_PNG_BUFFER,
      })
    }
  }

  /** Assert the manage page banner block shows a logo image (img with alt). Waits up to 10s for post-upload update. */
  async expectLogoVisibleInManageBanner() {
    await expect(this.page.getByTestId('manage-banner-button').locator('img[alt]').first()).toBeVisible({
      timeout: 10000,
    })
  }

  /** Close the Logo & banner modal via Done button. */
  async closeLogoBannerModal() {
    await this.page.getByTestId('manage-image-modal-done').click()
  }
}

module.exports = { RestaurantManagePage, MINIMAL_PNG_BUFFER }
