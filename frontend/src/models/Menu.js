/**
 * Menu model — from API (menus list/show/create/update).
 * Payload includes name (resolved from default locale) and translations (locale → { name, description }).
 * Use Menu.fromApi(apiResponse) when consuming menu endpoints.
 */

export default class Menu {
  constructor(data = {}) {
    this._uuid = data.uuid ?? null
    this._name = data.name ?? null
    this._is_active = data.is_active ?? true
    this._sort_order = data.sort_order ?? 0
    this._translations = data.translations && typeof data.translations === 'object' ? { ...data.translations } : {}
    this._created_at = data.created_at ?? null
    this._updated_at = data.updated_at ?? null
  }

  get uuid() { return this._uuid }
  get name() { return this._name }
  get is_active() { return this._is_active }
  get sort_order() { return this._sort_order }
  get translations() { return this._translations }
  get created_at() { return this._created_at }
  get updated_at() { return this._updated_at }

  /** Name in a given locale (from translations, or resolved name) */
  nameInLocale(locale) {
    const t = this._translations[locale]
    return t?.name ?? (locale ? '' : this._name ?? '')
  }

  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new Menu(data ?? {})
  }

  toJSON() {
    return {
      uuid: this._uuid,
      name: this._name,
      is_active: this._is_active,
      sort_order: this._sort_order,
      translations: { ...this._translations },
      created_at: this._created_at,
      updated_at: this._updated_at,
    }
  }
}
