/**
 * Legal content (Terms of Service, Privacy Policy).
 * Public GET: no auth, optional locale (en, es, ar). Superadmin: Bearer + superadmin, per-locale.
 * See docs/API-REFERENCE.md Legal content section.
 */

import api, { normalizeApiError } from './api'

/** Supported legal content locales: en, es, ar. */
export const LEGAL_LOCALES = ['en', 'es', 'ar']

/**
 * GET /api/legal/terms — public, no auth.
 * @param {string} [locale] — Optional. One of en, es, ar; backend falls back to en.
 * @returns {Promise<{ content: string }>}
 */
export async function getTerms(locale) {
  const params = locale ? { locale } : {}
  const { data } = await api.get('/legal/terms', { params })
  return { content: data?.data?.content ?? '' }
}

/**
 * GET /api/legal/privacy — public, no auth.
 * @param {string} [locale] — Optional. One of en, es, ar; backend falls back to en.
 * @returns {Promise<{ content: string }>}
 */
export async function getPrivacy(locale) {
  const params = locale ? { locale } : {}
  const { data } = await api.get('/legal/privacy', { params })
  return { content: data?.data?.content ?? '' }
}

/**
 * GET /api/superadmin/legal — superadmin only. Returns per-locale content.
 * @returns {Promise<{ en: { terms_of_service: string, privacy_policy: string }, es: {...}, ar: {...} }>}
 */
export async function getLegal() {
  const { data } = await api.get('/superadmin/legal')
  const d = data?.data ?? data ?? {}
  return {
    en: { terms_of_service: d.en?.terms_of_service ?? '', privacy_policy: d.en?.privacy_policy ?? '' },
    es: { terms_of_service: d.es?.terms_of_service ?? '', privacy_policy: d.es?.privacy_policy ?? '' },
    ar: { terms_of_service: d.ar?.terms_of_service ?? '', privacy_policy: d.ar?.privacy_policy ?? '' },
  }
}

/**
 * PATCH /api/superadmin/legal — superadmin only. Payload: per-locale objects.
 * @param {{ en?: { terms_of_service?: string, privacy_policy?: string }, es?: {...}, ar?: {...} }} payload
 * @returns {Promise<{ en: { terms_of_service: string, privacy_policy: string }, es: {...}, ar: {...} }>}
 */
export async function updateLegal(payload) {
  const { data } = await api.patch('/superadmin/legal', payload)
  const d = data?.data ?? data ?? {}
  return {
    en: { terms_of_service: d.en?.terms_of_service ?? '', privacy_policy: d.en?.privacy_policy ?? '' },
    es: { terms_of_service: d.es?.terms_of_service ?? '', privacy_policy: d.es?.privacy_policy ?? '' },
    ar: { terms_of_service: d.ar?.terms_of_service ?? '', privacy_policy: d.ar?.privacy_policy ?? '' },
  }
}

export { normalizeApiError }
