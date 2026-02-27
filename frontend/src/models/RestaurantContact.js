/**
 * Restaurant contact model — owner API payload (list/show/create/update).
 * Types: phone (whatsapp, mobile, phone, fax, other) and link (facebook, instagram, twitter, website).
 * API uses value (phone or URL); number is kept for backward compat (same as value for phone types, null for link types).
 * Use RestaurantContact.fromApi(apiResponse) when consuming contact endpoints.
 */

export const PHONE_TYPES = Object.freeze(['whatsapp', 'mobile', 'phone', 'fax', 'other'])
export const LINK_TYPES = Object.freeze(['facebook', 'instagram', 'twitter', 'website'])
export const CONTACT_TYPES = Object.freeze([...PHONE_TYPES, ...LINK_TYPES])

export function isLinkType(type) {
  return LINK_TYPES.includes(type)
}

export default class RestaurantContact {
  constructor(data = {}) {
    this._uuid = data.uuid ?? null
    this._type = data.type ?? 'phone'
    this._value = data.value ?? data.number ?? ''
    this._number = data.number ?? (PHONE_TYPES.includes(this._type) ? this._value : null)
    this._label = data.label ?? null
    this._isActive = data.is_active ?? true
    this._createdAt = data.created_at ?? data.createdAt ?? null
    this._updatedAt = data.updated_at ?? data.updatedAt ?? null
  }

  get uuid() {
    return this._uuid
  }

  get type() {
    return this._type
  }

  /** Phone number or URL — primary display and submit field. */
  get value() {
    return this._value
  }

  /** Backward compat: same as value for phone types, null for link types. */
  get number() {
    return this._number
  }

  get label() {
    return this._label
  }

  get is_active() {
    return this._isActive
  }

  get created_at() {
    return this._createdAt
  }

  get updated_at() {
    return this._updatedAt
  }

  /** Human-readable type label for display. */
  get typeLabel() {
    const labels = {
      whatsapp: 'WhatsApp',
      mobile: 'Mobile',
      phone: 'Phone',
      fax: 'Fax',
      other: 'Other',
      facebook: 'Facebook',
      instagram: 'Instagram',
      twitter: 'Twitter',
      website: 'Website',
    }
    return labels[this._type] ?? this._type
  }

  /** Build from API response (data object or { data } wrapper). */
  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new RestaurantContact(data ?? {})
  }

  /**
   * Plain object for submit (create/update). Omits uuid, created_at, updated_at for create.
   */
  toJSON() {
    return {
      uuid: this._uuid,
      type: this._type,
      value: this._value,
      number: PHONE_TYPES.includes(this._type) ? this._value : null,
      label: this._label ?? null,
      is_active: this._isActive,
      created_at: this._createdAt,
      updated_at: this._updatedAt,
    }
  }

  /** Payload for POST create (type, value, optional label, is_active). */
  toCreatePayload() {
    const payload = {
      type: this._type,
      value: String(this._value ?? '').trim(),
      label: this._label != null && String(this._label).trim() !== '' ? String(this._label).trim() : null,
      is_active: this._isActive,
    }
    if (PHONE_TYPES.includes(this._type)) payload.number = payload.value
    return payload
  }

  /** Payload for PATCH update (only changed or all optional fields). */
  toUpdatePayload() {
    const payload = {
      type: this._type,
      value: String(this._value ?? '').trim(),
      label: this._label != null && String(this._label).trim() !== '' ? String(this._label).trim() : null,
      is_active: this._isActive,
    }
    if (PHONE_TYPES.includes(this._type)) payload.number = payload.value
    return payload
  }
}
