/**
 * Combo entry: references a catalog menu item (and optional variant) with quantity and modifier.
 * Use fromApi() when consuming API responses.
 */
export default class ComboEntry {
  constructor(data = {}) {
    this.menu_item_uuid = data.menu_item_uuid ?? ''
    this.variant_uuid = data.variant_uuid ?? null
    this.quantity = typeof data.quantity === 'number' ? data.quantity : (data.quantity != null ? Number(data.quantity) : 1)
    this.modifier_label = data.modifier_label ?? null
  }

  static fromApi(data) {
    if (data == null || typeof data !== 'object') return new ComboEntry()
    return new ComboEntry(data)
  }

  toJSON() {
    return {
      menu_item_uuid: this.menu_item_uuid,
      variant_uuid: this.variant_uuid,
      quantity: this.quantity,
      modifier_label: this.modifier_label,
    }
  }
}
