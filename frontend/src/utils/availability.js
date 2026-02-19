/**
 * Utilities for operating hours / availability schedule validation.
 * Reusable for restaurant operating hours and menu item availability.
 */

export const DAY_KEYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']

/**
 * Parse a 24h time string (HH:MM or HH:MM:SS) to minutes since midnight.
 * @param {string} str - e.g. "09:00", "21:30", "14:00:00"
 * @returns {number} minutes since midnight, or NaN if invalid
 */
export function timeToMinutes(str) {
  if (typeof str !== 'string' || !str.trim()) return NaN
  const parts = str.trim().split(':')
  const h = parseInt(parts[0], 10)
  const m = parts.length >= 2 ? parseInt(parts[1], 10) : 0
  if (Number.isNaN(h) || Number.isNaN(m) || h < 0 || h > 23 || m < 0 || m > 59) return NaN
  return h * 60 + m
}

/**
 * Check if two time slots overlap (assuming same day).
 * Slots are { from: string, to: string } with 24h times.
 * @param {{ from: string, to: string }} slotA
 * @param {{ from: string, to: string }} slotB
 * @returns {boolean}
 */
export function slotsOverlap(slotA, slotB) {
  const aFrom = timeToMinutes(slotA.from)
  const aTo = timeToMinutes(slotA.to)
  const bFrom = timeToMinutes(slotB.from)
  const bTo = timeToMinutes(slotB.to)
  if (Number.isNaN(aFrom) || Number.isNaN(aTo) || Number.isNaN(bFrom) || Number.isNaN(bTo)) return false
  // Overlap: not disjoint. Disjoint if aTo <= bFrom OR bTo <= aFrom.
  return aTo > bFrom && bTo > aFrom
}

/**
 * Validate operating_hours: per-slot "from before to", and no overlapping timeslots per day.
 * @param {Record<string, { open?: boolean, slots?: Array<{ from: string, to: string }> }>} operatingHours - keyed by day (sunday..saturday)
 * @returns {{ valid: boolean, errors: Record<string, string> }} errors keyed by day
 */
export function validateOperatingHours(operatingHours) {
  const errors = {}
  if (!operatingHours || typeof operatingHours !== 'object') return { valid: true, errors }

  for (const dayKey of DAY_KEYS) {
    const day = operatingHours[dayKey]
    if (!day || !day.open || !Array.isArray(day.slots)) continue

    const dayLabel = dayKey.charAt(0).toUpperCase() + dayKey.slice(1)
    const slots = day.slots

    for (const slot of slots) {
      const fromM = timeToMinutes(slot.from)
      const toM = timeToMinutes(slot.to)
      if (Number.isNaN(fromM) || Number.isNaN(toM)) {
        errors[dayKey] = `Please enter valid times for ${dayLabel}.`
        break
      }
      if (fromM >= toM) {
        errors[dayKey] = `From must be before to for ${dayLabel}.`
        break
      }
    }
    if (errors[dayKey]) continue

    if (slots.length < 2) continue
    for (let i = 0; i < slots.length; i++) {
      for (let j = i + 1; j < slots.length; j++) {
        if (slotsOverlap(slots[i], slots[j])) {
          errors[dayKey] = `Time slots cannot overlap for ${dayLabel}.`
          break
        }
      }
      if (errors[dayKey]) break
    }
  }

  return {
    valid: Object.keys(errors).length === 0,
    errors,
  }
}

/**
 * Format operating_hours for display (e.g. on public restaurant page).
 * @param {Record<string, { open?: boolean, slots?: Array<{ from: string, to: string }> }> | null} operatingHours
 * @returns {{ day: string, label: string, text: string }[]} one entry per day in DAY_KEYS order
 */
export function formatOperatingHoursForDisplay(operatingHours) {
  if (!operatingHours || typeof operatingHours !== 'object') return []
  return DAY_KEYS.map((dayKey) => {
    const day = operatingHours[dayKey]
    const label = dayKey.charAt(0).toUpperCase() + dayKey.slice(1)
    if (!day || !day.open || !Array.isArray(day.slots) || day.slots.length === 0) {
      return { day: dayKey, label, text: 'Closed' }
    }
    const parts = day.slots.map((s) => `${s.from || '–'} – ${s.to || '–'}`).filter(Boolean)
    return { day: dayKey, label, text: parts.join(', ') }
  })
}
