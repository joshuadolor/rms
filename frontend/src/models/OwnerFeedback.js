/**
 * Owner feedback (feature request) model — shapes API payloads with defaults and derived values.
 * Use OwnerFeedback.fromApi(apiResponse) when consuming owner-feedback or superadmin/owner-feedbacks endpoints.
 *
 * API: uuid, title (nullable), message, status (pending | reviewed), created_at (ISO 8601),
 * submitter { uuid, name [, email ] }, restaurant { uuid, name } | null.
 * No internal id in responses; use uuid only.
 */

export default class OwnerFeedback {
  constructor(data = {}) {
    this._uuid = data.uuid ?? null
    this._title = data.title ?? null
    this._message = data.message ?? ''
    this._status = data.status ?? 'pending'
    this._createdAt = data.created_at ?? data.createdAt ?? null
    const sub = data.submitter ?? {}
    this._submitter = {
      uuid: sub.uuid ?? null,
      name: sub.name ?? '',
      email: sub.email ?? '',
    }
    const rest = data.restaurant ?? null
    this._restaurant = rest
      ? { uuid: rest.uuid ?? null, name: rest.name ?? '' }
      : null
  }

  get uuid() {
    return this._uuid
  }

  get title() {
    return this._title
  }

  get message() {
    return this._message
  }

  get status() {
    return this._status
  }

  get createdAt() {
    return this._createdAt
  }

  get submitter() {
    return this._submitter
  }

  get restaurant() {
    return this._restaurant
  }

  /** Submitter display: name or email or "Unknown". */
  get submitterLabel() {
    if (this._submitter.name) return this._submitter.name
    if (this._submitter.email) return this._submitter.email
    return 'Unknown'
  }

  /** Restaurant display: name or "—" when none. */
  get restaurantLabel() {
    return this._restaurant?.name ?? '—'
  }

  /** Message truncated for list view (e.g. 120 chars). */
  truncatedMessage(maxLength = 120) {
    if (!this._message) return ''
    return this._message.length <= maxLength
      ? this._message
      : this._message.slice(0, maxLength) + '…'
  }

  /** Formatted date for display (locale string). */
  get createdLabel() {
    if (!this._createdAt) return ''
    try {
      return new Date(this._createdAt).toLocaleString()
    } catch {
      return this._createdAt
    }
  }

  static fromApi(data) {
    if (data instanceof OwnerFeedback) return data
    const raw = data?.data ?? data
    return new OwnerFeedback(raw ?? {})
  }

  toJSON() {
    return {
      uuid: this._uuid,
      title: this._title,
      message: this._message,
      status: this._status,
      created_at: this._createdAt,
      submitter: { ...this._submitter },
      restaurant: this._restaurant ? { ...this._restaurant } : null,
    }
  }
}
