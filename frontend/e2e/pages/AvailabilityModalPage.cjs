// @ts-check
/**
 * Page object for the shared Availability modal (category or menu item).
 * Use after opening the modal from RestaurantMenuTabPage or CategoryMenuItemsPage.
 * All selectors are scoped to the modal (data-testid="availability-modal").
 */
const { expect } = require('@playwright/test')

class AvailabilityModalPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  _modal() {
    return this.page.getByTestId('availability-modal')
  }

  /** Assert the availability modal is open and visible */
  async expectModalOpen() {
    await expect(this._modal()).toBeVisible({ timeout: 10000 })
  }

  /** Assert the availability modal is closed (not visible) */
  async expectModalClosed() {
    await expect(this._modal()).not.toBeVisible()
  }

  /** Assert "All available" radio is selected (default) */
  async expectAllAvailableSelected() {
    const modal = this._modal()
    await expect(modal.getByRole('radio', { name: /All available/i }).first()).toBeChecked()
  }

  /** Select "Set specific times" (shows the day/slots schedule) */
  async selectSetSpecificTimes() {
    await this._modal().getByRole('radio', { name: /Set specific times/i }).click()
  }

  /** Assert the schedule section (day/slots grid) is visible inside the modal */
  async expectScheduleVisible() {
    await expect(this._modal().getByTestId('availability-modal-schedule')).toBeVisible()
  }

  /**
   * Set a slot time in the modal schedule.
   * @param {string} dayKey - e.g. 'monday'
   * @param {number} slotIndex - 0-based slot index
   * @param {'from'|'to'} fromOrTo - which time input
   * @param {string} time - e.g. '09:00'
   */
  async setSlotTime(dayKey, slotIndex, fromOrTo, time) {
    const day = this._modal().getByTestId(`availability-day-${dayKey}`)
    const label = fromOrTo === 'from' ? 'Opening time' : 'Closing time'
    const inputs = day.getByLabel(label)
    await inputs.nth(slotIndex).fill(time)
  }

  /** Click Save in the modal footer */
  async saveModal() {
    await this._modal().getByRole('button', { name: 'Save' }).click()
  }

  /** Click Cancel in the modal footer */
  async cancelModal() {
    await this._modal().getByRole('button', { name: 'Cancel' }).click()
  }

  /** Assert the modal shows an error (validation or API) containing the given text */
  async expectModalErrorToContain(text) {
    await expect(this._modal().getByRole('alert')).toContainText(text)
  }

  /** Assert a success toast with the given message is visible (after modal closes) */
  async expectSuccessToastWithMessage(message) {
    await expect(this.page.getByText(message)).toBeVisible({ timeout: 5000 })
  }
}

module.exports = { AvailabilityModalPage }
