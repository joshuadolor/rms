/**
 * Main app UI languages (dropdown in sidebar). Default from VITE_DEFAULT_LOCALE.
 */

export const APP_LOCALES = [
  { code: 'en', label: 'English', flag: '🇺🇸' },
  { code: 'es', label: 'Español', flag: '🇪🇸' },
  { code: 'ar', label: 'العربية', flag: '🇸🇦' },
]

export const APP_LOCALE_CODES = APP_LOCALES.map((l) => l.code)

const BY_CODE = Object.fromEntries(APP_LOCALES.map((l) => [l.code, l]))

export function getAppLocaleByCode(code) {
  return BY_CODE[code] ?? null
}

export function getAppLocaleLabel(code) {
  return BY_CODE[code]?.label ?? code
}

/** Default app locale from env (en if not set or invalid). */
export function getDefaultAppLocale() {
  const env = import.meta.env.VITE_DEFAULT_LOCALE ?? 'en'
  return APP_LOCALE_CODES.includes(env) ? env : 'en'
}

const STORAGE_KEY = 'rms_app_locale'

export function getStoredAppLocale() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return stored && APP_LOCALE_CODES.includes(stored) ? stored : null
  } catch {
    return null
  }
}

export function setStoredAppLocale(code) {
  try {
    if (APP_LOCALE_CODES.includes(code)) {
      localStorage.setItem(STORAGE_KEY, code)
    }
  } catch (_) {}
}
