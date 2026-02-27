/**
 * Helpers for restaurant contact display (public templates).
 * WhatsApp URL format: https://wa.me/<number without + and spaces>?text=<encoded message>
 */

/** Link types: value is a URL, use as href. */
export const LINK_TYPES = Object.freeze(['facebook', 'instagram', 'twitter', 'website'])

/** Default pre-filled message when opening WhatsApp chat. */
export const DEFAULT_WHATSAPP_MESSAGE = "Hi, I'd like to get in touch."

/**
 * Display value for a contact (value or number for backward compat).
 * @param {{ value?: string, number?: string }} c
 * @returns {string}
 */
export function contactValue(c) {
  const v = c?.value ?? c?.number ?? ''
  return typeof v === 'string' ? v : ''
}

/**
 * Whether the contact type is a link (URL).
 * @param {string} type
 */
export function isLinkType(type) {
  return LINK_TYPES.includes(type)
}

/**
 * Build WhatsApp chat URL. Number is normalized to digits only (no + or spaces).
 * @param {string} number - E.g. "+1 234 567 8900"
 * @param {string} [text] - Optional pre-filled message (default: DEFAULT_WHATSAPP_MESSAGE)
 * @returns {string} Full wa.me URL
 */
export function buildWhatsAppUrl(number, text = DEFAULT_WHATSAPP_MESSAGE) {
  if (!number || typeof number !== 'string') return ''
  const digits = number.replace(/\D/g, '')
  if (!digits) return ''
  const encoded = encodeURIComponent(text)
  return `https://wa.me/${digits}${encoded ? `?text=${encoded}` : ''}`
}
