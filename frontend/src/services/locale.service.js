/**
 * Locale and translation API. GET /locales is public; POST /translate requires auth.
 */

import api from './api'

/**
 * List supported locale codes (e.g. en, nl, ru) that can be installed per restaurant.
 * @returns {Promise<{ data: string[] }>}
 */
export async function getLocales() {
  const { data } = await api.get('/locales')
  return data
}

/**
 * Machine translate text. Requires auth. Returns 503 if LibreTranslate not configured.
 * @param {{ text: string, from_locale: string, to_locale: string }} params
 * @returns {Promise<{ translated_text: string }>}
 */
export async function translate(params) {
  const { data } = await api.post('/translate', params)
  return data
}

export const localeService = {
  getLocales,
  translate,
}

export default localeService
