/**
 * Category model — from API (categories list/show/create/update).
 * translations: { locale: { name, description? } }.
 */

export default class Category {
  constructor(data = {}) {
    this._uuid = data.uuid ?? null
    this._sort_order = data.sort_order ?? 0
    this._is_active = data.is_active !== false
    this._availability = data.availability ?? null
    this._translations = data.translations && typeof data.translations === 'object' ? { ...data.translations } : {}
    this._created_at = data.created_at ?? null
    this._updated_at = data.updated_at ?? null
  }

  get uuid() { return this._uuid }
  get sort_order() { return this._sort_order }
  get is_active() { return this._is_active }
  /** OperatingHours-shaped object or null (all available). */
  get availability() { return this._availability }
  get translations() { return this._translations }
  get created_at() { return this._created_at }
  get updated_at() { return this._updated_at }

  /** Name in a given locale */
  name(locale) {
    const t = this._translations[locale]
    return t?.name ?? ''
  }

  /** Description in a given locale */
  description(locale) {
    const t = this._translations[locale]
    return t?.description ?? ''
  }

  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new Category(data ?? {})
  }

  toJSON() {
    return {
      uuid: this._uuid,
      sort_order: this._sort_order,
      is_active: this._is_active,
      availability: this._availability,
      translations: { ...this._translations },
      created_at: this._created_at,
      updated_at: this._updated_at,
    }
  }
}
