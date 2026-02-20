/**
 * Format an ISO 8601 date string for display (date only, locale-aware).
 * @param {string|null|undefined} iso - ISO 8601 datetime string
 * @returns {string} Formatted date or empty string
 */
export function formatDate(iso) {
  if (!iso || typeof iso !== 'string') return ''
  try {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(d)
  } catch {
    return ''
  }
}

/**
 * Format a number as currency. Use the restaurant's currency in restaurant context.
 * @param {number} amount - Numeric price
 * @param {string} [currency='USD'] - ISO 4217 currency code (e.g. from restaurant.currency)
 * @returns {string} Formatted string (e.g. "$10.00" or "€10,00")
 */
export function formatCurrency(amount, currency = 'USD') {
  if (amount == null || Number.isNaN(Number(amount))) return '—'
  const code = (currency && String(currency).trim()) || 'USD'
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: code,
    minimumFractionDigits: 2,
  }).format(Number(amount))
}
