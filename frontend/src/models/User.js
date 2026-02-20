/**
 * User model — shapes API user payloads with defaults and derived values.
 * Use User.fromApi(apiResponse) when consuming auth/user endpoints.
 *
 * Backend response (AuthController::userPayload, ProfileController::userPayload):
 * { uuid, name, email, email_verified_at, pending_email?, is_paid? } — uuid is the public identifier (internal id not exposed).
 */

export default class User {
  constructor(data = {}) {
    this._id = data.uuid ?? data.id ?? data._id ?? null
    this._name = data.name ?? ''
    this._email = data.email ?? ''
    this._emailVerifiedAt = data.email_verified_at ?? data.emailVerifiedAt ?? null
    this._pendingEmail = data.pending_email ?? data.pendingEmail ?? null
    this._isPaid = data.is_paid === true
    // Optional if backend adds later (e.g. profile, OAuth)
    this._firstName = data.first_name ?? data.firstName ?? ''
    this._lastName = data.last_name ?? data.lastName ?? ''
    this._avatarUrl = data.avatar_url ?? data.avatarUrl ?? null
  }

  get id() {
    return this._id
  }

  get name() {
    return this._name
  }

  get email() {
    return this._email
  }

  get emailVerifiedAt() {
    return this._emailVerifiedAt
  }

  get isEmailVerified() {
    return this._emailVerifiedAt != null
  }

  /** New email awaiting verification (after profile email change). */
  get pendingEmail() {
    return this._pendingEmail
  }

  /** True if user can create custom menu item tags and use other paid features. */
  get isPaid() {
    return this._isPaid
  }

  get firstName() {
    return this._firstName
  }

  get lastName() {
    return this._lastName
  }

  /** Display name: name from API, or email local part, or "Guest". */
  get fullName() {
    if (this._name) return this._name
    if (this._firstName || this._lastName) {
      return [this._firstName, this._lastName].filter(Boolean).join(' ').trim()
    }
    if (this._email) return this._email.split('@')[0]
    return 'Guest'
  }

  get avatarUrl() {
    return this._avatarUrl
  }

  /** Build from API response. Use this when consuming user/auth APIs. */
  static fromApi(data) {
    if (data instanceof User) return data
    return new User(data)
  }

  /** Plain object for store/state or sending back to API if needed. */
  toJSON() {
    return {
      id: this._id,
      name: this._name,
      email: this._email,
      emailVerifiedAt: this._emailVerifiedAt,
      isEmailVerified: this.isEmailVerified,
      pendingEmail: this._pendingEmail,
      isPaid: this._isPaid,
      fullName: this.fullName,
      firstName: this._firstName,
      lastName: this._lastName,
      avatarUrl: this._avatarUrl,
    }
  }
}
