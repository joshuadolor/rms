/**
 * Public menu item from GET /api/public/restaurants/{slug}.
 * Types: simple | combo | with_variants.
 * Use PublicMenuItem.fromApi(apiItem) when building from API; defaults for every field.
 */

export default class PublicMenuItem {
  constructor(data = {}) {
    this._uuid = data.uuid ?? data._uuid ?? ''
    this._type = data.type ?? 'simple'
    this._name = data.name ?? ''
    this._description = data.description ?? null
    this._price = data.price ?? null
    this._sortOrder = data.sort_order ?? data.sortOrder ?? 0
    this._isAvailable = data.is_available ?? data.isAvailable ?? true
    this._availability = data.availability ?? null
    this._tags = Array.isArray(data.tags) ? data.tags.map((t) => ({ ...t })) : []
    this._imageUrl = data.image_url ?? data.imageUrl ?? null

    // Combo: only when type === 'combo'
    this._comboEntries = Array.isArray(data.combo_entries)
      ? data.combo_entries.map((e) => ({
          referenced_item_uuid: e.referenced_item_uuid ?? '',
          name: e.name ?? '',
          quantity: Number(e.quantity) || 0,
          modifier_label: e.modifier_label ?? null,
          variant_uuid: e.variant_uuid ?? null,
        }))
      : []

    // Variants: only when type === 'with_variants'
    this._variantOptionGroups = Array.isArray(data.variant_option_groups)
      ? data.variant_option_groups.map((g) => ({
          name: g.name ?? '',
          values: Array.isArray(g.values) ? [...g.values] : [],
        }))
      : []
    this._variantSkus = Array.isArray(data.variant_skus)
      ? data.variant_skus.map((s) => ({
          uuid: s.uuid ?? '',
          option_values: typeof s.option_values === 'object' && s.option_values !== null ? { ...s.option_values } : {},
          price: typeof s.price === 'number' ? s.price : null,
          image_url: s.image_url ?? s.imageUrl ?? null,
        }))
      : []
  }

  get uuid() {
    return this._uuid
  }

  get type() {
    return this._type
  }

  get name() {
    return this._name
  }

  get description() {
    return this._description
  }

  get price() {
    return this._price
  }

  get sort_order() {
    return this._sortOrder
  }

  get is_available() {
    return this._isAvailable
  }

  get availability() {
    return this._availability
  }

  get tags() {
    return this._tags
  }

  get image_url() {
    return this._imageUrl
  }

  get combo_entries() {
    return this._comboEntries
  }

  get variant_option_groups() {
    return this._variantOptionGroups
  }

  get variant_skus() {
    return this._variantSkus
  }

  /** Build one item from API payload. */
  static fromApi(apiItem) {
    return new PublicMenuItem(apiItem ?? {})
  }

  /** Plain object for template binding (same shape as API). */
  toJSON() {
    return {
      uuid: this._uuid,
      type: this._type,
      name: this._name,
      description: this._description,
      price: this._price,
      sort_order: this._sortOrder,
      is_available: this._isAvailable,
      availability: this._availability,
      tags: this._tags.map((t) => ({ ...t })),
      image_url: this._imageUrl,
      combo_entries: this._comboEntries.map((e) => ({ ...e })),
      variant_option_groups: this._variantOptionGroups.map((g) => ({ ...g })),
      variant_skus: this._variantSkus.map((s) => ({ ...s })),
    }
  }
}
