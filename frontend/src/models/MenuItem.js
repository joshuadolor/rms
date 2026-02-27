/**
 * Menu item (catalog or restaurant usage). Use fromApi() when consuming API responses.
 * Catalog items may be type: simple | combo | with_variants, with combo_entries or variant_option_groups + variant_skus.
 */
import ComboEntry from './ComboEntry.js'
import VariantOptionGroup from './VariantOptionGroup.js'
import VariantSku from './VariantSku.js'

export default class MenuItem {
  constructor(data = {}) {
    this.uuid = data.uuid ?? ''
    this.category_uuid = data.category_uuid ?? null
    this.sort_order = data.sort_order ?? 0
    this.type = data.type ?? 'simple'
    this.price = data.price != null ? Number(data.price) : null
    this.image_url = data.image_url ?? null
    this.translations = data.translations ?? {}
    this.created_at = data.created_at ?? null
    this.updated_at = data.updated_at ?? null
    this.restaurant_uuid = data.restaurant_uuid ?? null

    // Combo (when type === 'combo')
    this.combo_price = data.combo_price != null ? Number(data.combo_price) : null
    this.combo_entries = Array.isArray(data.combo_entries)
      ? data.combo_entries.map((e) => (e instanceof ComboEntry ? e : ComboEntry.fromApi(e)))
      : []

    // With variants (when type === 'with_variants')
    this.variant_option_groups = Array.isArray(data.variant_option_groups)
      ? data.variant_option_groups.map((g) => (g instanceof VariantOptionGroup ? g : VariantOptionGroup.fromApi(g)))
      : []
    this.variant_skus = Array.isArray(data.variant_skus)
      ? data.variant_skus.map((s) => (s instanceof VariantSku ? s : VariantSku.fromApi(s)))
      : []

    // Restaurant menu item visibility on public menu (default true when missing)
    this.is_active = data.is_active === undefined ? true : !!data.is_active
    // When false, item is shown on public menu but marked "Not Available" (default true when missing)
    this.is_available = data.is_available === undefined ? true : !!data.is_available
    // OperatingHours-shaped object or null (all available)
    this.availability = data.availability ?? null

    // Tags attached to this item (payload: [{ uuid, color, icon, text }])
    this.tags = Array.isArray(data.tags) ? data.tags.map((t) => ({ ...t })) : []

    // When item is a restaurant usage of a catalog item
    this.source_menu_item_uuid = data.source_menu_item_uuid ?? null
    this.source_variant_uuid = data.source_variant_uuid ?? null
    this.price_override = data.price_override != null ? Number(data.price_override) : null
    this.translation_overrides = data.translation_overrides ?? {}
    this.base_price = data.base_price != null ? Number(data.base_price) : null
    this.base_translations = data.base_translations ?? {}
    this.has_overrides = data.has_overrides ?? false
  }

  /** Whether this item has variants (type === 'with_variants' and has variant_skus). */
  get hasVariants() {
    return this.type === 'with_variants' && this.variant_skus.length > 0
  }

  /** Option group names in order (for variant display labels). */
  get variantOptionGroupNames() {
    return this.variant_option_groups.map((g) => g.name)
  }

  /** Effective price (base or override). For combo, may use combo_price; for with_variants, null at item level. */
  get effectivePrice() {
    if (this.source_menu_item_uuid != null && this.price_override != null) {
      return this.price_override
    }
    if (this.source_menu_item_uuid != null && this.base_price != null) {
      return this.base_price
    }
    if (this.type === 'combo' && this.combo_price != null) {
      return this.combo_price
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
    const out = {
      uuid: this.uuid,
      category_uuid: this.category_uuid,
      sort_order: this.sort_order,
      type: this.type,
      price: this.price,
      image_url: this.image_url,
      translations: this.translations,
      created_at: this.created_at,
      updated_at: this.updated_at,
      restaurant_uuid: this.restaurant_uuid,
      is_active: this.is_active,
      source_menu_item_uuid: this.source_menu_item_uuid,
      source_variant_uuid: this.source_variant_uuid,
      price_override: this.price_override,
      translation_overrides: this.translation_overrides,
      base_price: this.base_price,
      base_translations: this.base_translations,
      has_overrides: this.has_overrides,
      is_available: this.is_available,
      availability: this.availability,
      tags: this.tags.map((t) => ({ ...t })),
    }
    if (this.type === 'combo') {
      out.combo_price = this.combo_price
      out.combo_entries = this.combo_entries.map((e) => (e.toJSON ? e.toJSON() : e))
    }
    if (this.type === 'with_variants') {
      out.variant_option_groups = this.variant_option_groups.map((g) => (g.toJSON ? g.toJSON() : g))
      out.variant_skus = this.variant_skus.map((s) => (s.toJSON ? s.toJSON() : s))
    }
    return out
  }
}

export { ComboEntry, VariantOptionGroup, VariantSku }
