// @ts-check
/**
 * Page object for the Contact & links section on the Restaurant Profile tab:
 * add/edit form, list, toggle active, delete confirmation.
 */
const { expect } = require('@playwright/test')

class RestaurantContactsPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to restaurant manage page (Profile tab is default; Contact & links is on Profile). */
  async goToContacts(restaurantUuid) {
    await this.page.goto(`/app/restaurants/${restaurantUuid}`)
    await this.expectContactsPanelVisible()
  }

  /** Navigate directly to /app/restaurants/:uuid/contacts (redirects to manage with tab=profile). */
  async goToContactsByRoute(restaurantUuid) {
    await this.page.goto(`/app/restaurants/${restaurantUuid}/contacts`)
    await expect(this.page).toHaveURL(/tab=profile/, { timeout: 10000 })
    await this.expectContactsPanelVisible()
  }

  /** Assert the Contact & links section is visible (on Profile tab). */
  async expectContactsPanelVisible() {
    await expect(this.page.getByTestId('contact-and-links-section')).toBeVisible({ timeout: 10000 })
  }

  /** Click the "Add contact" button to open the form. */
  async clickAddContact() {
    await this.page.getByTestId('contact-add-button').click()
  }

  /** Assert the contact form card is visible. */
  async expectContactFormVisible() {
    await expect(this.page.getByTestId('contact-form-card')).toBeVisible()
    await expect(this.page.getByTestId('contact-form')).toBeVisible()
  }

  /** Assert the form heading shows "Add contact or link", "Add contact", or "Edit contact". */
  async expectContactFormHeading(text) {
    await expect(this.page.getByRole('heading', { name: text })).toBeVisible()
  }

  /** Set contact type via the Type select (value: whatsapp, mobile, phone, fax, other). */
  async setContactType(type) {
    await this.page.getByLabel('Type').selectOption(type)
  }

  /** Set the value field (Phone number or URL depending on type). */
  async setContactNumber(number) {
    await this.page.getByTestId('contact-value-input').fill(number)
  }

  /** Set the optional Label field. */
  async setContactLabel(label) {
    await this.page.getByLabel(/Label \(optional\)/).fill(label)
  }

  /** Set "Show on public page" switch (true = on, false = off). */
  async setContactShowOnPublic(checked) {
    const switchEl = this.page.getByRole('switch', { name: 'Show on public page' })
    await expect(switchEl).toBeVisible()
    const current = await switchEl.getAttribute('aria-checked')
    if (current !== String(checked)) {
      await switchEl.click()
    }
  }

  /** Submit the contact form (Add contact or Save). */
  async submitContactForm() {
    await this.page.getByTestId('contact-form-submit').click()
  }

  /** Cancel the contact form. */
  async cancelContactForm() {
    await this.page.getByTestId('contact-form-cancel').click()
  }

  /**
   * Assert a contact row is visible in the list.
   * Use number for phone types, value for link types (or number for both); optionally typeLabel and label.
   */
  async expectContactInList(options) {
    const list = this.page.getByTestId('contacts-list')
    await expect(list).toBeVisible()
    const searchText = options.value ?? options.number ?? ''
    const row = list.getByTestId('contact-item').filter({ hasText: searchText })
    await expect(row).toBeVisible()
    if (options.typeLabel) {
      await expect(row.getByText(options.typeLabel, { exact: true }).first()).toBeVisible()
    }
    if (options.label != null && options.label !== '') {
      await expect(row.getByText(options.label)).toBeVisible()
    }
  }

  /** Assert the contacts list is empty (no contact-item). */
  async expectContactsListEmpty() {
    await expect(this.page.getByTestId('contacts-list')).not.toBeVisible()
  }

  /** Assert the "No contacts or links yet" empty state is visible. */
  async expectNoContactsYetMessage() {
    await expect(this.page.getByText('No contacts or links yet. Add one so customers can reach you.')).toBeVisible()
  }

  /** Assert a validation error (role=alert or error text) is visible in the contact form. */
  async expectContactFormValidationError(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    const formCard = this.page.getByTestId('contact-form-card')
    await expect(formCard.getByRole('alert').filter({ hasText: pattern })).toBeVisible()
  }

  /** Return the contact item row locator that contains the given number, value (URL), or label. */
  _contactRow(numberOrLabelOrValue) {
    return this.page.getByTestId('contacts-list').getByTestId('contact-item').filter({ hasText: numberOrLabelOrValue })
  }

  /** Click the visibility toggle (Show/Hide on public page) for the contact that contains the given number or label. */
  async clickToggleActiveForContact(numberOrLabel) {
    const row = this._contactRow(numberOrLabel)
    await row.getByRole('button', { name: /(Hide|Show) this contact on public page/ }).click()
  }

  /** Click Edit for the contact that contains the given number or label. */
  async clickEditForContact(numberOrLabel) {
    const row = this._contactRow(numberOrLabel)
    await row.getByTestId('contact-edit-button').click()
  }

  /** Click Delete for the contact that contains the given number or label. */
  async clickDeleteForContact(numberOrLabel) {
    const row = this._contactRow(numberOrLabel)
    await row.getByTestId('contact-delete-button').click()
  }

  /** Assert the delete confirmation modal is visible. */
  async expectDeleteModalVisible() {
    await expect(this.page.getByTestId('contact-delete-modal')).toBeVisible()
    await expect(this.page.getByRole('heading', { name: 'Delete this contact?' })).toBeVisible()
  }

  /** Confirm delete in the modal. */
  async confirmDeleteContact() {
    await this.page.getByTestId('contact-delete-confirm').click()
  }

  /** Cancel delete in the modal. */
  async cancelDeleteContact() {
    await this.page.getByTestId('contact-delete-cancel').click()
  }

  /** Assert the delete modal is closed. */
  async expectDeleteModalClosed() {
    await expect(this.page.getByTestId('contact-delete-modal')).not.toBeVisible()
  }

  /** Assert the "Hidden" badge is visible on the contact row containing the given number or label. */
  async expectContactMarkedHidden(numberOrLabel) {
    const row = this._contactRow(numberOrLabel)
    await expect(row.getByText('Hidden')).toBeVisible()
  }

  /** Assert the "Hidden" badge is not visible on the contact row containing the given number or label. */
  async expectContactNotMarkedHidden(numberOrLabel) {
    const row = this._contactRow(numberOrLabel)
    await expect(row.getByText('Hidden')).not.toBeVisible()
  }
}

module.exports = { RestaurantContactsPage }
