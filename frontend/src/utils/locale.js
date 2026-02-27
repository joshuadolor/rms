/**
 * Human-readable display name for a locale code (e.g. en → "English", nl → "Nederlands").
 * Used on the public restaurant page language dropdown.
 * Falls back to the code if unknown.
 * @param {string} code - Locale code (e.g. "en", "nl")
 * @returns {string}
 */
const LOCALE_LABELS = {
  en: 'English',
  nl: 'Nederlands',
  de: 'Deutsch',
  fr: 'Français',
  es: 'Español',
  it: 'Italiano',
  pt: 'Português',
  pl: 'Polski',
  tr: 'Türkçe',
  ru: 'Русский',
  ja: '日本語',
  zh: '中文',
  ar: 'العربية',
}

export function localeDisplayName(code) {
  if (!code || typeof code !== 'string') return ''
  const normalized = code.split(/[-_]/)[0].toLowerCase()
  return LOCALE_LABELS[normalized] ?? code
}
