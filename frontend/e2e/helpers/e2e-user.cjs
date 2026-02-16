// @ts-check
/**
 * Shared E2E helpers for creating real users via the API.
 * Use in tests that need a real account (e.g. login, profile, email delivery).
 * Users created with e2e-*@example.com are removed by php artisan e2e:cleanup-users (see api/docs/e2e-cleanup.md).
 */

const API_ORIGIN = (process.env.E2E_API_BASE || process.env.VITE_PROXY_TARGET || '').replace(/\/$/, '')
const API_REGISTER = API_ORIGIN ? `${API_ORIGIN}/api/register` : '/api/register'

/**
 * Create a user via the real API (register). Use the returned credentials in later steps (e.g. login, profile).
 * @param {import('@playwright/test').APIRequestContext} request - Playwright request context (from test fixture).
 * @param {{ emailPrefix?: string, name?: string, password?: string }} [options]
 * @returns {Promise<{ email: string, password: string, name: string }>}
 */
async function createE2eUser(request, options = {}) {
  const emailPrefix = options.emailPrefix ?? 'e2e'
  const name = options.name ?? 'E2E User'
  const password = options.password ?? 'Password123'
  const email = `${emailPrefix}-${Date.now()}@example.com`
  const res = await request.post(API_REGISTER, {
    data: {
      name,
      email,
      password,
      password_confirmation: password,
    },
  })
  const body = await res.text()
  if (!res.ok()) {
    throw new Error(`createE2eUser failed (${res.status()}): ${body || 'no body'}`)
  }
  return { email, password, name }
}

module.exports = {
  createE2eUser,
  API_REGISTER,
}
