/**
 * Available languages for restaurants. Codes match backend (GET /locales should return these).
 * Order and display labels + emoji flags for UI.
 */

export const SUPPORTED_LOCALES = [
  { code: 'en', label: 'English', flag: '🇺🇸' },
  { code: 'es', label: 'Spanish', flag: '🇪🇸' },
  { code: 'zh', label: 'Chinese', flag: '🇨🇳' },
  { code: 'fil', label: 'Tagalog', flag: '🇵🇭' },
  { code: 'de', label: 'German', flag: '🇩🇪' },
  { code: 'fr', label: 'French', flag: '🇫🇷' },
  { code: 'uk', label: 'Ukrainian', flag: '🇺🇦' },
  { code: 'ru', label: 'Russian', flag: '🇷🇺' },
  { code: 'ja', label: 'Japanese', flag: '🇯🇵' },
  { code: 'nl', label: 'Dutch', flag: '🇳🇱' },
]

export const LOCALE_CODES = SUPPORTED_LOCALES.map((l) => l.code)

const BY_CODE = Object.fromEntries(SUPPORTED_LOCALES.map((l) => [l.code, l]))

export function getLocaleByCode(code) {
  return BY_CODE[code] ?? null
}

export function getLocaleLabel(code) {
  return BY_CODE[code]?.label ?? code
}

export function getLocaleFlag(code) {
  return BY_CODE[code]?.flag ?? '🌐'
}

/** Display string: flag + label (e.g. "🇬🇧 English") */
export function getLocaleDisplay(code) {
  const loc = BY_CODE[code]
  return loc ? `${loc.flag} ${loc.label}` : code
}
