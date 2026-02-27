// @ts-check
/**
 * Page object for the public restaurant page (/r/:slug). Used to assert
 * visible sections such as Opening hours when operating_hours are set.
 */
const { expect } = require('@playwright/test')

class PublicRestaurantPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page
  }

  /** Navigate to the public restaurant page by slug (path /r/:slug). Waits for content (main or error) to be ready. */
  async goToPublicBySlug(slug) {
    await this.page.goto(`/r/${slug}`)
    await expect(this.page.locator('.public-restaurant-page')).toBeVisible({ timeout: 10000 })
    // Wait for fetch to finish: either main content or error card
    await expect(
      this.page.getByRole('main').or(this.page.locator('.rms-public__error'))
    ).toBeVisible({ timeout: 10000 })
  }

  /**
   * Navigate to the public restaurant page by slug and wait for Vue app to mount.
   * Returns the response from the document request so callers can assert response.ok() / 200.
   */
  async goToPublicBySlugAndGetResponse(slug) {
    const response = await this.page.goto(`/r/${slug}`)
    await expect(this.page.locator('.public-restaurant-page')).toBeVisible({ timeout: 10000 })
    await expect(
      this.page.getByRole('main').or(this.page.locator('.rms-public__error'))
    ).toBeVisible({ timeout: 10000 })
    return response
  }

  /** Assert the "Opening hours" section (heading and content) is visible. */
  async expectOpeningHoursSectionVisible() {
    await expect(this.page.getByRole('heading', { name: 'Opening hours' })).toBeVisible()
  }

  /** Assert the "Opening hours" section is not present (e.g. when no hours set). */
  async expectOpeningHoursSectionNotVisible() {
    await expect(this.page.getByRole('heading', { name: 'Opening hours' })).not.toBeVisible()
  }

  /** Assert the "Not available" label is visible on the public menu (for items with is_available: false). Scoped to in-page .rms-menu to avoid matching the View Menu modal. */
  async expectNotAvailablePillVisible() {
    const menu = this.page.locator('.rms-menu')
    await expect(menu.getByText('Not available')).toBeVisible()
  }

  /** Assert the "Not available" label is not visible on the page. */
  async expectNotAvailablePillNotVisible() {
    await expect(this.page.getByText('Not available')).not.toBeVisible()
  }

  /** Assert "Price on request" is visible (simple/combo item with no price). Scoped to in-page .rms-menu to avoid matching the View Menu modal. */
  async expectPriceOnRequestVisible() {
    const menu = this.page.locator('.rms-menu')
    await expect(menu.getByText('Price on request')).toBeVisible()
  }

  /** Assert the Combo contents list (aria-label "Combo contents") is visible. */
  async expectComboContentsListVisible() {
    await expect(this.page.getByRole('list', { name: 'Combo contents' })).toBeVisible()
  }

  /** Assert the Size and price options list (variant SKUs, aria-label "Size and price options") is visible. */
  async expectVariantSizeAndPriceOptionsVisible() {
    await expect(this.page.getByRole('list', { name: 'Size and price options' })).toBeVisible()
  }

  /** Assert a given text string is visible in the in-page public menu (e.g. item name, price). Scoped to .rms-menu to avoid matching the View Menu modal. */
  async expectTextVisible(text) {
    const menu = this.page.locator('.rms-menu')
    await expect(menu.getByText(text)).toBeVisible()
  }

  /** Assert the given text is visible in the main content area (e.g. About description, any section). */
  async expectMainContainsText(text) {
    await expect(this.page.getByRole('main').getByText(text)).toBeVisible()
  }

  /** Assert a tag icon with the given tooltip text (title) is visible on the in-page public menu. Scoped to .rms-menu to avoid matching the View Menu modal. */
  async expectTagIconWithTitleVisible(tagText) {
    const menu = this.page.locator('.rms-menu')
    await expect(menu.getByTitle(tagText)).toBeVisible()
  }

  /**
   * Assert a tag pill label with the given text is visible in the in-page public menu.
   * Use when the API returns tags with text (e.g. "Spicy"). Scoped to .rms-menu.
   */
  async expectTagPillVisibleInMenu(tagText) {
    const menu = this.page.locator('.rms-menu')
    await expect(menu.getByText(tagText)).toBeVisible()
  }

  /** Assert a formatted price string is visible in the in-page public menu (e.g. "$10.00"). Scoped to .rms-menu. */
  async expectPriceVisibleInMenu(priceText) {
    await this.expectTextVisible(priceText)
  }

  /** Assert a menu item with the given name is visible (in the menu list). */
  async expectMenuItemNameVisible(itemName) {
    const menu = this.page.locator('.rms-menu')
    await expect(menu).toBeVisible({ timeout: 5000 })
    await expect(menu.getByText(itemName)).toBeVisible({ timeout: 5000 })
  }

  /**
   * Assert the formatted availability text (category or item) is visible in the in-page public menu.
   * Use the exact string the frontend shows (e.g. "Mon–Fri 11:00–15:00"). Scoped to .rms-menu.
   */
  async expectAvailabilityTextVisibleInMenu(availabilityText) {
    const menu = this.page.locator('.rms-menu')
    await expect(menu.getByText(availabilityText)).toBeVisible()
  }

  /**
   * Assert the given availability text is not visible in the in-page public menu (e.g. when availability is null).
   * Scoped to .rms-menu to avoid matching the View Menu modal.
   */
  async expectAvailabilityTextNotVisibleInMenu(availabilityText) {
    const menu = this.page.locator('.rms-menu')
    await expect(menu.getByText(availabilityText)).not.toBeVisible()
  }

  /**
   * Assert "Always available" is not shown in the menu (current behavior: no label when availability is null).
   * Scoped to .rms-menu.
   */
  async expectNoAlwaysAvailableLabelInMenu() {
    const menu = this.page.locator('.rms-menu')
    await expect(menu.getByText(/always available/i)).not.toBeVisible()
  }

  // --- Reviews (approved feedbacks) and submit feedback form ---

  /** Assert the Reviews section is visible (section id=reviews). */
  async expectReviewsSectionVisible() {
    await expect(this.page.locator('#reviews')).toBeVisible()
  }

  /** Assert the empty reviews message is visible. */
  async expectNoReviewsYet() {
    await expect(
      this.page.getByText('No reviews yet. Be the first to leave feedback below.')
    ).toBeVisible()
  }

  /** Assert a review (approved feedback) with the given author name or text snippet is visible. */
  async expectReviewVisible(nameOrText) {
    await expect(this.page.getByText(nameOrText)).toBeVisible()
  }

  /** Assert the "Leave your feedback" form heading is visible. */
  async expectFeedbackFormVisible() {
    await expect(this.page.getByRole('heading', { name: 'Leave your feedback' })).toBeVisible()
    await expect(this.page.getByRole('button', { name: 'Send feedback' })).toBeVisible()
  }

  /** Assert the Reviews & feedback section (id=reviews) contains both the section heading and the feedback form. */
  async expectFeedbackFormInReviewsSection() {
    const section = this.page.locator('#reviews')
    await expect(section).toBeVisible()
    await expect(section.getByRole('heading', { name: 'Reviews & feedback' })).toBeVisible()
    await expect(section.getByRole('heading', { name: 'Leave your feedback' })).toBeVisible()
    await expect(section.getByRole('button', { name: 'Send feedback' })).toBeVisible()
  }

  /** Set rating by clicking the nth star (1–5). */
  async setFeedbackRating(stars) {
    await this.page.getByRole('button', { name: `${stars} star${stars > 1 ? 's' : ''}` }).click()
  }

  /** Fill the "Your name" field in the feedback form. */
  async setFeedbackName(name) {
    await this.page.getByLabel(/your name/i).fill(name)
  }

  /** Fill the "Your message" textarea in the feedback form. */
  async setFeedbackMessage(message) {
    await this.page.getByLabel(/your message/i).fill(message)
  }

  /** Submit the feedback form. */
  async submitFeedbackForm() {
    await this.page.getByRole('button', { name: 'Send feedback' }).click()
  }

  /** Assert the success message (role="status") after submitting feedback is visible. */
  async expectFeedbackSuccessMessage(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('status').filter({ hasText: pattern })).toBeVisible({
      timeout: 5000,
    })
  }

  /** Assert an error message (role="alert") in the feedback form is visible. */
  async expectFeedbackErrorMessage(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByRole('alert').filter({ hasText: pattern })).toBeVisible()
  }

  /** Assert validation error for a specific field (e.g. "Please choose a rating"). */
  async expectFeedbackFieldError(messageOrPattern) {
    const pattern =
      typeof messageOrPattern === 'string' ? new RegExp(messageOrPattern, 'i') : messageOrPattern
    await expect(this.page.getByText(pattern)).toBeVisible()
  }

  // --- Logo and hero (public restaurant page with logo/banner optimization) ---

  /** Assert the header logo (image in banner) is visible. Restaurant name is the img alt. */
  async expectNavLogoVisible(restaurantName) {
    const header = this.page.getByRole('banner')
    await expect(header.getByRole('img', { name: restaurantName })).toBeVisible()
  }

  /** Assert the hero logo block (logo above restaurant name in hero) is visible. */
  async expectHeroLogoBlockVisible() {
    await expect(this.page.getByTestId('public-hero-logo')).toBeVisible()
  }

  /** Assert the hero logo block is not present (restaurant has no logo). */
  async expectHeroLogoBlockNotVisible() {
    await expect(this.page.getByTestId('public-hero-logo')).not.toBeVisible()
  }

  // --- Main public page structure (header, hero, menu, about, reviews, footer) ---

  /** Assert the header (banner) shows the restaurant name (heading h1, or text/span, or img alt). */
  async expectHeaderWithNameVisible(restaurantName) {
    const banner = this.page.getByRole('banner')
    await expect(banner).toBeVisible()
    const heading = banner.getByRole('heading', { level: 1, name: restaurantName })
    const textOrAlt = banner.getByText(restaurantName)
    await expect(heading.or(textOrAlt)).toBeVisible()
  }

  /** Assert the hero section (in main) shows the restaurant name (h2 or any visible text). */
  async expectHeroWithNameVisible(restaurantName) {
    const main = this.page.getByRole('main')
    await expect(main).toBeVisible()
    const heading = main.getByRole('heading', { level: 2, name: restaurantName })
    const text = main.getByText(restaurantName)
    await expect(heading.or(text)).toBeVisible()
  }

  /** Assert the Menu section heading is visible (section id=menu; heading "Our Menu"). */
  async expectMenuSectionVisible() {
    await expect(this.page.getByRole('heading', { name: 'Our Menu' }).first()).toBeVisible()
  }

  /** Assert the menu section contains at least one menu item (in .rms-menu). */
  async expectMenuHasAtLeastOneItem() {
    const menuSection = this.page.locator('.rms-menu')
    await expect(menuSection).toBeVisible()
    await expect(menuSection.locator('.rms-menu-item').first()).toBeVisible({ timeout: 5000 })
  }

  /** Assert the About section (heading "About") is visible. */
  async expectAboutSectionVisible() {
    await expect(this.page.getByRole('heading', { name: 'About', exact: true })).toBeVisible()
  }

  /** Assert the About section is not present (no description). Uses exact match so "No About Place" is not matched. */
  async expectAboutSectionNotVisible() {
    await expect(this.page.getByRole('heading', { name: 'About', exact: true })).not.toBeVisible()
  }

  /** Assert the footer (contentinfo) is visible and contains the restaurant name. */
  async expectFooterWithNameVisible(restaurantName) {
    const footer = this.page.getByRole('contentinfo')
    await expect(footer).toBeVisible()
    await expect(footer.getByText(restaurantName).first()).toBeVisible()
  }

  /** Assert template-1 (Template1 component) is rendered (data-testid=public-template-1). */
  async expectTemplate1Applied() {
    await expect(this.page.getByTestId('public-template-1')).toBeVisible({ timeout: 10000 })
  }

  /** Assert template-2 (Template2 component) is rendered (data-testid=public-template-2). */
  async expectTemplate2Applied() {
    await expect(this.page.getByTestId('public-template-2')).toBeVisible({ timeout: 10000 })
  }

  /** Assert template-1 header and main sections are visible (header, hero, menu, about, reviews, footer). */
  async expectTemplate1HeaderAndSectionsVisible(restaurantName) {
    await this.expectTemplate1Applied()
    await this.expectHeaderWithNameVisible(restaurantName)
    await this.expectHeroWithNameVisible(restaurantName)
    await this.expectMenuSectionVisible()
    await this.expectMenuHasAtLeastOneItem()
    await this.expectAboutSectionVisible()
    await this.expectReviewsSectionVisible()
    await this.expectFooterWithNameVisible(restaurantName)
  }

  /** Assert template-2 header and main sections are visible (header, hero, menu, about, reviews, footer). */
  async expectTemplate2HeaderAndSectionsVisible(restaurantName) {
    await this.expectTemplate2Applied()
    await this.expectHeaderWithNameVisible(restaurantName)
    await this.expectHeroWithNameVisible(restaurantName)
    await this.expectMenuSectionVisible()
    await this.expectMenuHasAtLeastOneItem()
    await this.expectAboutSectionVisible()
    await this.expectReviewsSectionVisible()
    await this.expectFooterWithNameVisible(restaurantName)
  }

  /** Assert the public page does not show "Template 1" or "Template 2" text (dev badges removed for guests). */
  async expectNoTemplateLabelVisible() {
    await expect(this.page.getByText('Template 1', { exact: true })).toHaveCount(0)
    await expect(this.page.getByText('Template 2', { exact: true })).toHaveCount(0)
  }

  // --- Mobile View Menu: sticky button and full-page menu modal ---

  /** Mobile viewport (375×667) so the sticky "View Menu" bar is visible (hidden at 768px+). */
  async setMobileViewport() {
    await this.page.setViewportSize({ width: 375, height: 667 })
  }

  /** Assert the sticky "View Menu" button is visible (mobile only). */
  async expectStickyViewMenuButtonVisible() {
    await expect(this.page.getByRole('button', { name: /view menu/i })).toBeVisible()
  }

  /** Assert the sticky "View Menu" button is not visible (e.g. desktop). */
  async expectStickyViewMenuButtonHidden() {
    await expect(this.page.getByRole('button', { name: /view menu/i })).not.toBeVisible()
  }

  /** Click the sticky "View Menu" button to open the menu modal. */
  async clickStickyViewMenuButton() {
    await this.page.getByRole('button', { name: /view menu/i }).click()
  }

  /** Assert the menu modal (dialog "Menu") is open: dialog visible and Surprise me or category content present. */
  async expectMenuModalOpen() {
    const dialog = this.page.getByRole('dialog', { name: 'Menu' })
    await expect(dialog).toBeVisible({ timeout: 5000 })
    const surpriseOrCategory = dialog.locator('.rms-menu-modal__surprise, .rms-menu-modal__category-head').first()
    await expect(surpriseOrCategory).toBeVisible({ timeout: 3000 })
  }

  /** Assert the menu modal is closed (dialog not visible or hidden). */
  async expectMenuModalClosed() {
    await expect(this.page.getByRole('dialog', { name: 'Menu' })).not.toBeVisible()
  }

  /** Close the menu modal via the header "Close menu" button. */
  async closeMenuModalViaHeaderButton() {
    await this.page.getByRole('dialog', { name: 'Menu' }).getByRole('button', { name: 'Close menu' }).first().click()
  }

  /** Close the menu modal via Escape key. */
  async closeMenuModalViaEscape() {
    await this.page.keyboard.press('Escape')
  }

  /** In the open modal: assert a category header with the given name is visible (accordion head only). */
  async expectModalCategoryHeaderVisible(categoryName) {
    const dialog = this.page.getByRole('dialog', { name: 'Menu' })
    const categoryHead = dialog.locator('.rms-menu-modal__category-head').filter({ hasText: new RegExp(`^${categoryName}`) })
    await expect(categoryHead).toBeVisible()
  }

  /** In the open modal: click a category header to expand/collapse it. */
  async clickModalCategoryHeader(categoryName) {
    const dialog = this.page.getByRole('dialog', { name: 'Menu' })
    await dialog.locator('.rms-menu-modal__category-head').filter({ hasText: new RegExp(`^${categoryName}`) }).click()
  }

  /** In the open modal: assert a menu item name or price text is visible in the modal content. */
  async expectModalMenuItemVisible(text) {
    const dialog = this.page.getByRole('dialog', { name: 'Menu' })
    await expect(dialog.getByText(text)).toBeVisible()
  }

  /** In the open modal: click the "Surprise me" button. */
  async clickSurpriseMeInModal() {
    const dialog = this.page.getByRole('dialog', { name: 'Menu' })
    await dialog.getByRole('button', { name: /surprise me/i }).click()
  }

  /** In the open modal: assert at least one item has the highlight class (after Surprise me; waits for picking animation). */
  async expectModalItemHighlighted() {
    const dialog = this.page.getByRole('dialog', { name: 'Menu' })
    await expect(dialog.locator('.rms-menu-modal__item--highlight')).toBeVisible({ timeout: 6000 })
  }

  /** In the open modal: assert the "Surprise me" button is disabled (e.g. empty menu). */
  async expectSurpriseMeDisabled() {
    const dialog = this.page.getByRole('dialog', { name: 'Menu' })
    await expect(dialog.getByRole('button', { name: /surprise me/i })).toBeDisabled()
  }

  /** In the open modal: assert a tag with the given tooltip (title) is visible. */
  async expectModalTagWithTitleVisible(tooltipText) {
    const dialog = this.page.getByRole('dialog', { name: 'Menu' })
    await expect(dialog.getByTitle(tooltipText)).toBeVisible()
  }

  // --- Language dropdown (header; shown only when restaurant has more than one language) ---

  /**
   * Assert the public page language dropdown (data-testid="public-language-dropdown") is visible.
   * Template1/Template2 render the dropdown twice (desktop + mobile header); we assert the first instance is visible.
   */
  async expectLanguageDropdownVisible() {
    await expect(this.page.getByTestId('public-language-dropdown').first()).toBeVisible()
  }

  /** Assert the public page language dropdown is not visible (single-language restaurant). */
  async expectLanguageDropdownNotVisible() {
    await expect(this.page.getByTestId('public-language-dropdown')).toHaveCount(0)
  }

  /**
   * Select a language by locale code (e.g. 'nl') in the header language dropdown.
   * The page will refetch with ?locale= and update content.
   * Uses the first dropdown instance (desktop header); both instances share the same Vue state.
   */
  async selectLanguage(localeCode) {
    const dropdown = this.page
      .getByTestId('public-language-dropdown')
      .first()
      .getByRole('combobox', { name: 'Select language' })
    await dropdown.selectOption({ value: localeCode })
  }

  /** Assert the current URL contains the given locale query (e.g. ?locale=nl or &locale=nl). */
  async expectUrlHasLocale(localeCode) {
    await expect(this.page).toHaveURL(new RegExp(`[?&]locale=${encodeURIComponent(localeCode)}(&|$)`))
  }

  // --- Contact section (public template) ---

  /** Assert the Contact Us section (heading) is visible. */
  async expectContactSectionVisible() {
    await expect(this.page.getByRole('heading', { name: 'Contact Us' })).toBeVisible()
  }

  /** Assert the "No contact numbers or links listed" message is visible in the contact section. */
  async expectNoContactNumbersListed() {
    await expect(this.page.getByText('No contact numbers or links listed.')).toBeVisible()
  }

  /** Assert an active contact with the given number or label text is visible (in main or #contact). */
  async expectActiveContactVisible(numberOrLabel) {
    const contactSection = this.page.locator('#contact').or(this.page.getByRole('main'))
    await expect(contactSection.getByText(numberOrLabel)).toBeVisible()
  }

  /**
   * Assert a WhatsApp link is present with href matching wa.me and the given number (digits only).
   * Number can be with spaces/plus; we assert the link href contains wa.me and the normalized digits.
   */
  async expectWhatsAppLinkWithNumber(number) {
    const digits = (number || '').replace(/\D/g, '')
    const link = this.page.locator(`a[href*="wa.me/${digits}"]`)
    await expect(link).toBeVisible()
    await expect(link).toHaveAttribute('href', new RegExp(`wa\\.me/${digits.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(\\?|$)`))
  }

  /** Assert the given text (e.g. an inactive contact number) is not visible in the contact area. */
  async expectContactNotVisible(numberOrLabel) {
    const contactSection = this.page.locator('#contact').or(this.page.getByRole('main'))
    await expect(contactSection.getByText(numberOrLabel)).not.toBeVisible()
  }

  /**
   * Assert a link (e.g. link-type contact) is visible in the contact section with the given href.
   * Use to verify link types are clickable with value as href.
   */
  async expectContactLinkWithHref(href) {
    const contactSection = this.page.locator('#contact').or(this.page.getByRole('main'))
    const link = contactSection.locator(`a[href="${href}"]`)
    await expect(link).toBeVisible()
  }
}

module.exports = { PublicRestaurantPage }
