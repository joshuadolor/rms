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
