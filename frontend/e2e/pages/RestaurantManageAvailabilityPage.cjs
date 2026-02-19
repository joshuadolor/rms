// @ts-check
/**
 * Page object for Restaurant Manage: Availability tab (weekly schedule) and
 * Profile tab save flow for operating hours. All selectors and element
 * interactions are encapsulated here. Used for E2E tests that assert schedule
 * visibility, slot editing, overlap validation, and successful save.
 */
const { expect } = require('@playwright/test')

class RestaurantManageAvailabilityPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to restaurant manage page (Profile tab by default). */
  async goToManagePage(restaurantUuid) {
    await this.page.goto(`/app/restaurants/${restaurantUuid}`)
    await expect(this.page.getByTestId('restaurant-manage-page')).toBeVisible({ timeout: 10000 })
  }

  /** Switch to the Availability tab. */
  async goToAvailabilityTab() {
    await this.page.getByRole('tab', { name: 'Availability' }).click()
    await expect(this.page.getByTestId('manage-panel-availability')).toBeVisible()
  }

  /** Switch to the Profile tab. */
  async goToProfileTab() {
    await this.page.getByRole('tab', { name: 'Profile' }).click()
    await expect(this.page.getByTestId('manage-panel-profile')).toBeVisible()
  }

  /** Assert the weekly availability schedule container is visible. */
  async expectScheduleVisible() {
    await expect(this.page.getByTestId('availability-schedule')).toBeVisible()
  }

  /** Assert a given day row is visible (dayKey e.g. 'monday', 'sunday'). */
  async expectDayVisible(dayKey) {
    await expect(this.page.getByTestId(`availability-day-${dayKey}`)).toBeVisible()
  }

  /**
   * Set one time in a slot. field is 'from' or 'to'; value is HH:MM (24h).
   * Slot index 0 = first slot row for that day.
   */
  async setSlotTime(dayKey, slotIndex, field, value) {
    const day = this.page.getByTestId(`availability-day-${dayKey}`)
    const timeInputs = day.locator('input[type="time"]')
    const index = slotIndex * 2 + (field === 'from' ? 0 : 1)
    await timeInputs.nth(index).fill(value)
  }

  /** Add another time slot for the given day (clicks "Add another time slot" within that day). */
  async addSlotForDay(dayKey) {
    const day = this.page.getByTestId(`availability-day-${dayKey}`)
    await day.getByRole('button', { name: 'Add another time slot' }).click()
  }

  /** Click Save changes on the Profile tab (restaurant form submit). */
  async clickSaveChanges() {
    await this.page.getByTestId('form-submit').click()
  }

  /** Click Save on the Availability tab (saves operating hours only). */
  async clickSaveOnAvailabilityTab() {
    await this.page.getByTestId('availability-save-button').click()
  }

  /**
   * Set a day open or closed. When closing, unchecks "X open for business"; when opening, checks it.
   * Clicks the label that wraps the checkbox so the visible toggle responds (checkbox is sr-only).
   * @param {string} dayKey - e.g. 'monday', 'sunday'
   * @param {boolean} open - true = open for business, false = closed
   */
  async setDayOpen(dayKey, open) {
    const label = dayKey.charAt(0).toUpperCase() + dayKey.slice(1)
    const checkbox = this.page.getByRole('checkbox', { name: `${label} open for business` })
    const isChecked = await checkbox.isChecked()
    if (isChecked !== open) {
      await checkbox.click({ force: true })
    }
  }

  /** Assert the given day row shows "Closed" (disabled inputs with value Closed). */
  async expectDayShowsClosed(dayKey) {
    const day = this.page.getByTestId(`availability-day-${dayKey}`)
    await expect(day.locator('input[value="Closed"]').first()).toBeVisible()
  }

  /** Assert the availability summary error block contains the given text. */
  async expectAvailabilitySummaryErrorToContain(text) {
    await expect(this.page.getByTestId('availability-summary-error')).toContainText(text)
  }

  /** Assert the per-day error for the given day contains the given text. */
  async expectDayErrorToContain(dayKey, text) {
    await expect(this.page.getByTestId(`availability-day-error-${dayKey}`)).toContainText(text)
  }

  /** Assert the form error block is visible (e.g. overlap summary). */
  async expectFormErrorVisible() {
    const formError = this.page.getByTestId('form-error')
    await expect(formError).toBeVisible()
    await expect(formError).not.toHaveClass(/sr-only/)
  }

  /** Assert the form error block contains the given text. */
  async expectFormErrorToContain(text) {
    await expect(this.page.getByTestId('form-error')).toContainText(text)
  }

  /** Assert the form error is hidden (sr-only or not visible). */
  async expectFormErrorHidden() {
    const formError = this.page.getByTestId('form-error')
    await expect(formError).toHaveClass(/sr-only/)
  }

  /** Assert the availability summary error (above schedule) is visible. Shown on Availability tab. */
  async expectAvailabilitySummaryErrorVisible() {
    await expect(this.page.getByTestId('availability-summary-error')).toBeVisible()
  }

  /** Assert the per-day error for the given day is visible (e.g. availability-day-error-monday). */
  async expectDayErrorVisible(dayKey) {
    await expect(this.page.getByTestId(`availability-day-error-${dayKey}`)).toBeVisible()
  }

  /** Assert a success toast with the given message is visible. */
  async expectSuccessToastWithMessage(message) {
    await expect(this.page.getByText(message)).toBeVisible({ timeout: 5000 })
  }
}

module.exports = { RestaurantManageAvailabilityPage }
