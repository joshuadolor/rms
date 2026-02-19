/**
 * Menu item (catalog or restaurant usage). Use fromApi() when consuming API responses.
 */
export default class MenuItem {
  constructor(data = {}) {
    this.uuid = data.uuid ?? ''
    this.category_uuid = data.category_uuid ?? null
    this.sort_order = data.sort_order ?? 0
    this.price = data.price != null ? Number(data.price) : null
    this.translations = data.translations ?? {}
    this.created_at = data.created_at ?? null
    this.updated_at = data.updated_at ?? null
    this.restaurant_uuid = data.restaurant_uuid ?? null

    // When item is a restaurant usage of a catalog item
    this.source_menu_item_uuid = data.source_menu_item_uuid ?? null
    this.price_override = data.price_override != null ? Number(data.price_override) : null
    this.translation_overrides = data.translation_overrides ?? {}
    this.base_price = data.base_price != null ? Number(data.base_price) : null
    this.base_translations = data.base_translations ?? {}
    this.has_overrides = data.has_overrides ?? false
  }

  /** Effective price (base or override). */
  get effectivePrice() {
    if (this.source_menu_item_uuid != null && this.price_override != null) {
      return this.price_override
    }
    if (this.source_menu_item_uuid != null && this.base_price != null) {
      return this.base_price
    }
    return this.price
  }

  /** Effective name for a locale (base + overrides). */
  effectiveName(locale) {
    const over = this.translation_overrides?.[locale]?.name
    if (over !== undefined && over !== '') return over
    const base = this.base_translations?.[locale]?.name ?? this.translations?.[locale]?.name
    return base ?? ''
  }

  /** Effective description for a locale. */
  effectiveDescription(locale) {
    if (this.translation_overrides?.[locale] && 'description' in this.translation_overrides[locale]) {
      return this.translation_overrides[locale].description ?? null
    }
    return this.base_translations?.[locale]?.description ?? this.translations?.[locale]?.description ?? null
  }

  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new MenuItem(data)
  }

  toJSON() {
    return {
      uuid: this.uuid,
      category_uuid: this.category_uuid,
      sort_order: this.sort_order,
      price: this.price,
      translations: this.translations,
      created_at: this.created_at,
      updated_at: this.updated_at,
      restaurant_uuid: this.restaurant_uuid,
      source_menu_item_uuid: this.source_menu_item_uuid,
      price_override: this.price_override,
      translation_overrides: this.translation_overrides,
      base_price: this.base_price,
      base_translations: this.base_translations,
      has_overrides: this.has_overrides,
    }
  }
}
