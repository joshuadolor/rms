/**
 * Feedback model — shapes API feedback payloads with defaults and derived values.
 * Use Feedback.fromApi(data) when consuming owner list/update or public submit/create responses.
 *
 * Owner payload: uuid, rating (1-5), text, name, is_approved, created_at, updated_at.
 * Public approved (in GET public restaurant): uuid, rating, text, name, created_at.
 */

export default class Feedback {
  constructor(data = {}) {
    this._uuid = data.uuid ?? ''
    this._rating = typeof data.rating === 'number' ? data.rating : (data.rating != null ? Number(data.rating) : 0)
    this._text = data.text ?? ''
    this._name = data.name ?? ''
    this._isApproved = data.is_approved ?? data.isApproved ?? false
    this._createdAt = data.created_at ?? data.createdAt ?? null
    this._updatedAt = data.updated_at ?? data.updatedAt ?? null
  }

  get uuid() {
    return this._uuid
  }

  get rating() {
    return this._rating
  }

  get text() {
    return this._text
  }

  get name() {
    return this._name
  }

  get is_approved() {
    return this._isApproved
  }

  get created_at() {
    return this._createdAt
  }

  get updated_at() {
    return this._updatedAt
  }

  /** Display label for approval state. */
  get approvalLabel() {
    return this._isApproved ? 'Approved' : 'Pending'
  }

  /** Build from API response. Accepts { data } or raw payload. */
  static fromApi(apiResponse) {
    const data = apiResponse?.data ?? apiResponse
    return new Feedback(data ?? {})
  }

  /** Plain object for template binding (snake_case to match API). */
  toJSON() {
    return {
      uuid: this._uuid,
      rating: this._rating,
      text: this._text,
      name: this._name,
      is_approved: this._isApproved,
      created_at: this._createdAt,
      updated_at: this._updatedAt,
    }
  }
}
