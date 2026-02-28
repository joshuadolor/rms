import PublicMenuItem from './PublicMenuItem.js'

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
    this._menuItems = Array.isArray(data.menu_items) ? data.menu_items.map((i) => PublicMenuItem.fromApi(i)) : []
    this._menuGroups = Array.isArray(data.menu_groups)
      ? data.menu_groups.map((g) => ({
          category_name: g.category_name ?? 'Menu',
          category_uuid: g.category_uuid ?? null,
          availability: g.availability ?? null,
          image_url: g.image_url ?? null,
          items: Array.isArray(g.items) ? g.items.map((i) => PublicMenuItem.fromApi(i)) : [],
        }))
      : []
    this._feedbacks = Array.isArray(data.feedbacks) ? data.feedbacks.map((f) => ({ ...f })) : []
    this._contacts = Array.isArray(data.contacts)
      ? data.contacts.map((c) => ({
          uuid: c.uuid ?? null,
          type: c.type ?? 'phone',
          value: c.value ?? c.number ?? '',
          number: c.number ?? '',
          label: c.label ?? null,
        }))
      : []
    const viewer = (data.viewer && typeof data.viewer === 'object') ? data.viewer : {}
    this._viewer = {
      is_owner: viewer.is_owner === true,
      owner_admin_url: typeof viewer.owner_admin_url === 'string' && viewer.owner_admin_url.trim() !== ''
        ? viewer.owner_admin_url
        : null,
      needs_data: viewer.needs_data === true,
    }
    this._template = data.template ?? 'template-1'
    this._yearEstablished = data.year_established ?? data.yearEstablished ?? null
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

  /** Menu groups with PublicMenuItem instances in items. */
  get menu_groups() {
    return this._menuGroups
  }

  get feedbacks() {
    return this._feedbacks
  }

  /** Active contacts only (public payload: uuid, type, number, label). */
  get contacts() {
    return this._contacts
  }

  get viewer() {
    return this._viewer
  }

  get template() {
    return this._template
  }

  get year_established() {
    return this._yearEstablished
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
      menu_items: this._menuItems.map((i) => (i.toJSON ? i.toJSON() : { ...i })),
      menu_groups: this._menuGroups.map((g) => ({
        category_name: g.category_name,
        category_uuid: g.category_uuid,
        availability: g.availability ?? null,
        image_url: g.image_url ?? null,
        items: (g.items ?? []).map((i) => (i.toJSON ? i.toJSON() : { ...i })),
      })),
      feedbacks: this._feedbacks.map((f) => ({ ...f })),
      contacts: this._contacts.map((c) => ({ ...c })),
      viewer: {
        is_owner: this._viewer.is_owner === true,
        owner_admin_url: this._viewer.owner_admin_url ?? null,
        needs_data: this._viewer.needs_data === true,
      },
      template: this._template,
      year_established: this._yearEstablished,
    }
  }
}
