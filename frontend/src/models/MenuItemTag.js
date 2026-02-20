/**
 * Menu item tag (e.g. Spicy, Vegan). Use fromApi() when consuming GET /api/menu-item-tags or tag payloads on menu items.
 * No internal id; only uuid is used (per docs/API-REFERENCE.md).
 */

export default class MenuItemTag {
  constructor(data = {}) {
    this.uuid = data.uuid ?? ''
    this.color = data.color ?? '#6b7280'
    this.icon = data.icon ?? ''
    this.text = data.text ?? ''
    this.is_default = data.is_default === true
  }

  static fromApi(apiTag) {
    const data = apiTag?.data ?? apiTag
    return new MenuItemTag(data ?? {})
  }

  toJSON() {
    return {
      uuid: this.uuid,
      color: this.color,
      icon: this.icon,
      text: this.text,
      is_default: this.is_default,
    }
  }
}
