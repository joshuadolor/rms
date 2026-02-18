/**
 * Restaurant model — shapes API restaurant payloads with defaults and derived values.
 * Use Restaurant.fromApi(apiResponse) when consuming restaurant list/detail/update endpoints.
 *
 * Backend (RestaurantController::restaurantPayload): uuid, name, slug, public_url,
 * address, latitude, longitude, phone, email, website, social_links, default_locale,
 * languages, logo_url, banner_url, created_at, updated_at.
 * operating_hours may be returned by some endpoints or set client-side.
 */

const DEFAULT_SOCIAL_LINKS = {
  facebook: '',
  instagram: '',
  twitter: '',
  linkedin: '',
}

export default class Restaurant {
  constructor(data = {}) {
    this._uuid = data.uuid ?? null
    this._name = data.name ?? ''
    this._slug = data.slug ?? null
    this._publicUrl = data.public_url ?? data.publicUrl ?? ''
    this._address = data.address ?? ''
    this._tagline = data.tagline ?? ''
    this._latitude = data.latitude ?? null
    this._longitude = data.longitude ?? null
    this._phone = data.phone ?? ''
    this._email = data.email ?? ''
    this._website = data.website ?? ''
    this._description = data.description ?? ''
    const rawSocial = data.social_links ?? data.socialLinks ?? {}
    this._socialLinks = {
      facebook: rawSocial.facebook ?? '',
      instagram: rawSocial.instagram ?? '',
      twitter: rawSocial.twitter ?? '',
      linkedin: rawSocial.linkedin ?? '',
    }
    this._defaultLocale = data.default_locale ?? data.defaultLocale ?? 'en'
    this._languages = Array.isArray(data.languages) ? [...data.languages] : []
    this._logoUrl = data.logo_url ?? data.logoUrl ?? null
    this._bannerUrl = data.banner_url ?? data.bannerUrl ?? null
    this._operatingHours = data.operating_hours ?? data.operatingHours ?? {}
    this._createdAt = data.created_at ?? data.createdAt ?? null
    this._updatedAt = data.updated_at ?? data.updatedAt ?? null
    this._currency = data.currency ?? 'USD'
  }

  get uuid() {
    return this._uuid
  }

  get name() {
    return this._name
  }

  get slug() {
    return this._slug
  }

  get publicUrl() {
    return this._publicUrl
  }

  get address() {
    return this._address
  }

  get tagline() {
    return this._tagline
  }

  get latitude() {
    return this._latitude
  }

  get longitude() {
    return this._longitude
  }

  get phone() {
    return this._phone
  }

  get email() {
    return this._email
  }

  get website() {
    return this._website
  }

  get description() {
    return this._description
  }

  get social_links() {
    return this._socialLinks
  }

  get default_locale() {
    return this._defaultLocale
  }

  get languages() {
    return this._languages
  }

  get logo_url() {
    return this._logoUrl
  }

  get banner_url() {
    return this._bannerUrl
  }

  get operating_hours() {
    return this._operatingHours
  }

  get created_at() {
    return this._createdAt
  }

  get updated_at() {
    return this._updatedAt
  }

  get currency() {
    return this._currency
  }

  /** Build from API response (data object or { data } wrapper). Use when consuming restaurant APIs. */
  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new Restaurant(data ?? {})
  }

  /**
   * Plain object for v-model / template binding and for sending payloads to API.
   * Components can use this when they need a reactive object; assign back to model for updates.
   */
  toJSON() {
    return {
      uuid: this._uuid,
      name: this._name,
      slug: this._slug,
      public_url: this._publicUrl,
      address: this._address,
      tagline: this._tagline,
      latitude: this._latitude,
      longitude: this._longitude,
      phone: this._phone,
      email: this._email,
      website: this._website,
      description: this._description,
      social_links: { ...this._socialLinks },
      default_locale: this._defaultLocale,
      languages: [...this._languages],
      logo_url: this._logoUrl,
      banner_url: this._bannerUrl,
      operating_hours: this._operatingHours && typeof this._operatingHours === 'object' ? { ...this._operatingHours } : {},
      created_at: this._createdAt,
      updated_at: this._updatedAt,
      currency: this._currency,
    }
  }
}
