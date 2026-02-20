/**
 * Variant option group: name + ordered values (e.g. Type: ["Hawaiian", "Pepperoni"]).
 * Use fromApi() when consuming API responses.
 */
export default class VariantOptionGroup {
  constructor(data = {}) {
    this.name = data.name ?? ''
    this.values = Array.isArray(data.values) ? [...data.values] : []
  }

  static fromApi(data) {
    if (data == null || typeof data !== 'object') return new VariantOptionGroup()
    return new VariantOptionGroup(data)
  }

  toJSON() {
    return {
      name: this.name,
      values: [...this.values],
    }
  }
}
