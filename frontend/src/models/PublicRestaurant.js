/**
 * Public restaurant payload from GET /api/public/restaurants/{slug}.
 * Use PublicRestaurant.fromApi(apiResponse) when consuming the public endpoint.
 * No internal id; payload matches docs/API-REFERENCE.md (name, slug, operating_hours, menu_items, etc.).
 */

export default class PublicRestaurant {
  constructor(data = {}) {
    this._name = data.name ?? ''
    this._tagline = data.tagline ?? null
    this._primaryColor = data.primary_color ?? data.primaryColor ?? null
    this._slug = data.slug ?? ''
    this._logoUrl = data.logo_url ?? data.logoUrl ?? null
    this._bannerUrl = data.banner_url ?? data.bannerUrl ?? null
    this._defaultLocale = data.default_locale ?? data.defaultLocale ?? 'en'
    this._currency = data.currency ?? 'USD'
    this._operatingHours = data.operating_hours ?? data.operatingHours ?? null
    this._languages = Array.isArray(data.languages) ? [...data.languages] : []
    this._locale = data.locale ?? this._defaultLocale
    this._description = data.description ?? null
    this._menuItems = Array.isArray(data.menu_items) ? data.menu_items.map((i) => ({ ...i })) : []
    this._feedbacks = Array.isArray(data.feedbacks) ? data.feedbacks.map((f) => ({ ...f })) : []
  }

  get name() {
    return this._name
  }

  get tagline() {
    return this._tagline
  }

  get primary_color() {
    return this._primaryColor
  }

  get slug() {
    return this._slug
  }

  get logo_url() {
    return this._logoUrl
  }

  get banner_url() {
    return this._bannerUrl
  }

  get default_locale() {
    return this._defaultLocale
  }

  get currency() {
    return this._currency
  }

  get operating_hours() {
    return this._operatingHours
  }

  get languages() {
    return this._languages
  }

  get locale() {
    return this._locale
  }

  get description() {
    return this._description
  }

  get menu_items() {
    return this._menuItems
  }

  get feedbacks() {
    return this._feedbacks
  }

  /** Build from API response (e.g. { data } from getPublicRestaurant). */
  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new PublicRestaurant(data ?? {})
  }

  /** Plain object for template binding (same shape as API). */
  toJSON() {
    return {
      name: this._name,
      tagline: this._tagline,
      primary_color: this._primaryColor,
      slug: this._slug,
      logo_url: this._logoUrl,
      banner_url: this._bannerUrl,
      default_locale: this._defaultLocale,
      currency: this._currency,
      operating_hours: this._operatingHours,
      languages: [...this._languages],
      locale: this._locale,
      description: this._description,
      menu_items: this._menuItems.map((i) => ({ ...i })),
      feedbacks: this._feedbacks.map((f) => ({ ...f })),
    }
  }
}
