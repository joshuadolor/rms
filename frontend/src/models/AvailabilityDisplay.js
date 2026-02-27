/**
 * Display model for menu item/category availability on the public menu.
 * Wraps raw availability (operating_hours shape) and exposes display logic.
 * Use when rendering availability and "unavailable now" styling.
 */
import { isAvailableNow, formatAvailabilityForDisplay } from '@/utils/availability'

export default class AvailabilityDisplay {
  constructor(availability) {
    this._availability = availability == null || typeof availability !== 'object' ? null : availability
    this._hasSchedule =
      this._availability != null &&
      typeof this._availability === 'object' &&
      Object.keys(this._availability).length > 0
  }

  /** True if there is an availability schedule (may be all closed). */
  get hasSchedule() {
    return this._hasSchedule
  }

  /**
   * @param {Date} [now]
   * @returns {boolean}
   */
  isAvailableNow(now = new Date()) {
    return isAvailableNow(this._availability, now)
  }

  /**
   * Has a schedule and is not available at the given time (use for opacity 0.8).
   * @param {Date} [now]
   * @returns {boolean}
   */
  isUnavailableNow(now = new Date()) {
    return this._hasSchedule && !this.isAvailableNow(now)
  }

  /**
   * Formatted label: full schedule when available now, or "Currently not available. Available only …" when not.
   * @param {Date} [now]
   * @returns {string | null}
   */
  label(now = new Date()) {
    return formatAvailabilityForDisplay(this._availability, now)
  }

  /**
   * @param {Record<string, { open?: boolean, slots?: Array<{ from: string, to: string }> }> | null} availability
   * @returns {AvailabilityDisplay}
   */
  static from(availability) {
    return new AvailabilityDisplay(availability)
  }
}
