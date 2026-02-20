/**
 * Variant SKU: one combination of option values with price (and optional image).
 * option_values: Record<groupName, value> e.g. { Type: "Hawaiian", Size: "Small" }
 * Use fromApi() when consuming API responses.
 */
export default class VariantSku {
  constructor(data = {}) {
    this.uuid = data.uuid ?? ''
    this.option_values = data.option_values != null && typeof data.option_values === 'object' ? { ...data.option_values } : {}
    this.price = data.price != null ? Number(data.price) : 0
    this.image_url = data.image_url ?? null
  }

  /**
   * Display label for this variant (e.g. "Hawaiian, Small").
   * Option order follows keys order; for stable display you may pass group names in order.
   * @param {string[]} [groupOrder] - Optional order of option group names for display
   */
  displayLabel(groupOrder = null) {
    const ov = this.option_values
    if (!ov || Object.keys(ov).length === 0) return '—'
    const keys = groupOrder && groupOrder.length ? groupOrder.filter((k) => ov[k] != null) : Object.keys(ov)
    return keys.map((k) => ov[k]).filter(Boolean).join(', ')
  }

  static fromApi(data) {
    if (data == null || typeof data !== 'object') return new VariantSku()
    return new VariantSku(data)
  }

  toJSON() {
    return {
      uuid: this.uuid,
      option_values: { ...this.option_values },
      price: this.price,
      image_url: this.image_url,
    }
  }
}
