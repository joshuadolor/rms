// @ts-check
/**
 * E2E tests for authentication (email/password only; SSO is out of scope).
 * Most tests mock the API so they run without the backend.
 * The final "Email delivery" describe uses the real API + Mailhog (no mocks) and is skipped if API or Mailhog are unreachable.
 * After the Email delivery tests, e2e:cleanup-users is run to remove created users (see api/docs/e2e-cleanup.md).
 */
const path = require('path')
const fs = require('fs')
const { execSync } = require('child_process')
const { test, expect } = require('@playwright/test')
const { createE2eUser } = require('./helpers/e2e-user.cjs')

const MOCK_VERIFIED_USER = {
  id: 1,
  name: 'Test Owner',
  email: 'verified@example.com',
  email_verified_at: '2025-01-01T00:00:00.000000Z',
}

const MOCK_TOKEN = 'mock-sanctum-token'

test.describe('Landing', () => {
  test('shows sign in and create account options', async ({ page }) => {
    await page.goto('/')
    await expect(page.getByRole('link', { name: /login/i })).toBeVisible()
    await expect(page.getByRole('link', { name: /create one/i })).toBeVisible()
  })

  test('Login link goes to /login', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('link', { name: /login/i }).first().click()
    await expect(page).toHaveURL(/\/login/)
    await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible()
  })

  test('Create one link goes to /register', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('link', { name: /create one/i }).click()
    await expect(page).toHaveURL(/\/register/)
    await expect(page.getByRole('heading', { name: /create an account/i })).toBeVisible()
  })
})

test.describe('Login', () => {
  test('shows error for invalid credentials', async ({ page }) => {
    await page.route('**/api/login', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The provided credentials are incorrect.',
          errors: { email: ['The provided credentials are incorrect.'] },
        }),
      })
    })

    await page.goto('/login')
    await page.getByPlaceholder(/you@example\.com/i).fill('wrong@example.com')
    await page.getByPlaceholder(/••••••••/).fill('wrongpass')
    await page.getByRole('button', { name: /sign in/i }).click()

    await expect(page.locator('#login-form-error')).toContainText(/credentials are incorrect|sign in failed/i)
    await expect(page).toHaveURL(/\/login/)
  })

  test('successful login redirects to /app and shows Sign out', async ({ page }) => {
    await page.route('**/api/login', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Logged in successfully.',
          user: MOCK_VERIFIED_USER,
          token: MOCK_TOKEN,
          token_type: 'Bearer',
        }),
      })
    })

    await page.goto('/login')
    await page.getByPlaceholder(/you@example\.com/i).fill('verified@example.com')
    await page.getByPlaceholder(/••••••••/).fill('password123')
    await page.getByRole('button', { name: /sign in/i }).click()

    await expect(page).toHaveURL(/\/app/)
    await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible()
    await expect(page.getByText(/welcome,/i)).toBeVisible()
  })

  test('Forgot password link goes to /forgot-password', async ({ page }) => {
    await page.goto('/login')
    await page.getByRole('link', { name: /forgot password/i }).click()
    await expect(page).toHaveURL(/\/forgot-password/)
    await expect(page.getByRole('heading', { name: /forgot password/i })).toBeVisible()
  })
})

test.describe('Register', () => {
  test('step 1: name and email then Continue shows step 2', async ({ page }) => {
    await page.goto('/register')
    await page.getByPlaceholder(/jane smith/i).fill('Jane Doe')
    await page.getByPlaceholder(/you@example\.com/i).fill('jane@example.com')
    await page.getByRole('button', { name: 'Continue', exact: true }).click()

    await expect(page.getByLabel(/^password$/i)).toBeVisible()
    await expect(page.getByLabel(/confirm password/i)).toBeVisible()
    await expect(page.getByRole('button', { name: /create account/i })).toBeVisible()
  })

  test('step 1: validation shows error for empty name', async ({ page }) => {
    await page.goto('/register')
    await page.getByPlaceholder(/you@example\.com/i).fill('jane@example.com')
    await page.getByRole('button', { name: 'Continue', exact: true }).click()

    await expect(page.getByText('Please enter your name.')).toBeVisible()
  })

  test('full registration redirects to verify-email', async ({ page }) => {
    await page.route('**/api/register', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Registered. Please verify your email using the link we sent you.',
          user: { id: 2, name: 'Jane', email: 'jane@example.com', email_verified_at: null },
        }),
      })
    })

    await page.goto('/register')
    await page.getByPlaceholder(/jane smith/i).fill('Jane Doe')
    await page.getByPlaceholder(/you@example\.com/i).fill('jane@example.com')
    await page.getByRole('button', { name: 'Continue', exact: true }).click()

    await page.getByLabel(/^password$/i).fill('Password123')
    await page.getByLabel(/confirm password/i).fill('Password123')
    await page.getByRole('checkbox', { name: /terms of service/i }).check()
    await page.getByRole('button', { name: /create account/i }).click()

    await expect(page).toHaveURL(/\/verify-email/)
    await expect(page.getByRole('heading', { name: 'Check your email' })).toBeVisible()
  })
})

test.describe('Forgot password', () => {
  test('submit shows success message', async ({ page }) => {
    await page.route('**/api/forgot-password', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'If that email exists in our system, we have sent a password reset link.',
        }),
      })
    })

    await page.goto('/forgot-password')
    await page.getByPlaceholder(/you@example\.com/i).fill('user@example.com')
    await page.getByRole('button', { name: /send reset link/i }).click()

    await expect(page.getByText('Check your inbox').first()).toBeVisible()
    await expect(page.getByText(/user@example\.com/)).toBeVisible()
  })

  test('empty email shows validation error', async ({ page }) => {
    await page.goto('/forgot-password')
    await page.getByPlaceholder(/you@example\.com/i).fill('')
    await page.getByRole('button', { name: /send reset link/i }).click()
    await expect(page.getByText('Please enter your email address.')).toBeVisible()
  })

  test('invalid email shows validation error', async ({ page }) => {
    await page.goto('/forgot-password')
    await page.getByPlaceholder(/you@example\.com/i).fill('not-an-email')
    await page.getByRole('button', { name: /send reset link/i }).click()
    await expect(page.getByText('Please enter a valid email address.')).toBeVisible()
  })

  test('Back to sign in goes to /login', async ({ page }) => {
    await page.goto('/forgot-password')
    await page.getByRole('link', { name: /back to sign in/i }).click()
    await expect(page).toHaveURL(/\/login/)
  })
})

test.describe('Reset password', () => {
  test('without token/email shows invalid link message', async ({ page }) => {
    await page.goto('/reset-password')
    await expect(page.getByRole('alert')).toContainText(/invalid reset link/i)
  })

  test('with token and email shows form', async ({ page }) => {
    await page.goto('/reset-password?token=abc123&email=user@example.com')
    await expect(page.getByRole('heading', { name: /reset password/i })).toBeVisible()
    await expect(page.getByLabel(/new password/i)).toBeVisible()
    await expect(page.getByLabel(/confirm password/i)).toBeVisible()
    await expect(page.getByRole('button', { name: /reset password/i })).toBeVisible()
  })
})

test.describe('Verify email page', () => {
  test('shows resend option after register flow', async ({ page }) => {
    await page.goto('/verify-email?email=user@example.com')
    await expect(page.getByRole('heading', { name: 'Check your email' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Resend link' })).toBeVisible()
  })
})

test.describe('Route guards', () => {
  test('unauthenticated visit to /app redirects to login with redirect query', async ({ page }) => {
    await page.goto('/app')
    await expect(page).toHaveURL(/\/login/)
    await expect(page).toHaveURL(/\?redirect=%2Fapp|redirect=\/app/)
  })

  test('authenticated user visiting /login redirects to /app', async ({ page }) => {
    await page.route('**/api/login', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Logged in successfully.',
          user: MOCK_VERIFIED_USER,
          token: MOCK_TOKEN,
          token_type: 'Bearer',
        }),
      })
    })
    // App.vue onMounted calls getMe() when token exists; mock so it doesn't redirect to Landing
    await page.route('**/api/user', (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ user: MOCK_VERIFIED_USER }),
      })
    })

    await page.goto('/login')
    await page.getByPlaceholder(/you@example\.com/i).fill('verified@example.com')
    await page.getByPlaceholder(/••••••••/).fill('password123')
    await page.getByRole('button', { name: /sign in/i }).click()

    await expect(page).toHaveURL(/\/app/)

    await page.goto('/login')
    await expect(page).toHaveURL(/\/app/)
  })
})

test.describe('Logout', () => {
  test('Sign out redirects to landing and /app requires login again', async ({ page }) => {
    await page.route('**/api/login', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Logged in successfully.',
          user: MOCK_VERIFIED_USER,
          token: MOCK_TOKEN,
          token_type: 'Bearer',
        }),
      })
    })
    await page.route('**/api/logout', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({ status: 200, contentType: 'application/json', body: '{}' })
    })

    await page.goto('/login')
    await page.getByPlaceholder(/you@example\.com/i).fill('verified@example.com')
    await page.getByPlaceholder(/••••••••/).fill('password123')
    await page.getByRole('button', { name: /sign in/i }).click()

    await expect(page).toHaveURL(/\/app/)
    await page.getByRole('button', { name: /sign out/i }).click()
    // Wait for logout API to complete so store state is cleared
    await page.waitForResponse((res) => res.url().includes('/api/logout') && res.request().method() === 'POST', { timeout: 5000 }).catch(() => null)
    // After logout, /app should redirect to login (proves auth state was cleared)
    await page.goto('/app')
    await expect(page).toHaveURL(/\/login/)
  })
})

// ---- Email delivery (real API + Mailhog; skipped if unreachable) ----
const MAILHOG_URL = process.env.MAILHOG_URL || 'http://localhost:8025'
const API_ORIGIN = (process.env.E2E_API_BASE || process.env.VITE_PROXY_TARGET || '').replace(/\/$/, '')
const API_HEALTH = API_ORIGIN ? `${API_ORIGIN}/api/health` : '/api/health'
const API_REGISTER = API_ORIGIN ? `${API_ORIGIN}/api/register` : '/api/register'
const API_FORGOT_PASSWORD = API_ORIGIN ? `${API_ORIGIN}/api/forgot-password` : '/api/forgot-password'

function getMessageCount(body) {
  if (Array.isArray(body)) return body.length
  if (body != null && typeof body === 'object') {
    if (body.total != null) return body.total
    if (body.count != null) return body.count
    const list = body.messages ?? body.items
    if (Array.isArray(list)) return list.length
  }
  return 0
}

function getMessagesList(body) {
  if (Array.isArray(body)) return body
  const o = (body ?? {})
  const list = o.messages ?? o.items
  return Array.isArray(list) ? list : []
}

function messageIsTo(message, email) {
  const norm = (s) => String(s || '').toLowerCase().trim()
  const target = norm(email)
  const headers = message?.Content?.Headers ?? message?.Content?.headers ?? {}
  const headerTo = headers['To'] ?? headers['to']
  if (headerTo) {
    const toStr = Array.isArray(headerTo) ? headerTo.join(' ') : String(headerTo)
    if (norm(toStr).includes(target)) return true
  }
  const toList = message?.To
  if (Array.isArray(toList)) {
    for (const p of toList) {
      const mailbox = p?.Mailbox ?? p?.mailbox ?? ''
      const domain = p?.Domain ?? p?.domain ?? ''
      if (mailbox && domain && norm(`${mailbox}@${domain}`) === target) return true
      if (norm(mailbox + '@' + domain).includes(target)) return true
    }
  }
  return false
}

let emailDeliveryReachable = null

async function checkEmailDeliveryReachable(request) {
  if (emailDeliveryReachable !== null) return emailDeliveryReachable
  try {
    const mailhogRes = await request.get(`${MAILHOG_URL}/api/v1/messages`)
    if (!mailhogRes.ok()) return false
    const apiHealthRes = await request.get(API_HEALTH)
    if (!apiHealthRes.ok()) return false
    emailDeliveryReachable = true
    return true
  } catch {
    emailDeliveryReachable = false
    return false
  }
}

function runE2eCleanup() {
  const cmd = process.env.E2E_CLEANUP_CMD
  if (cmd) {
    try {
      execSync(cmd, { stdio: 'pipe', encoding: 'utf8' })
    } catch (e) {
      console.warn('[e2e] Cleanup command failed (is the API container running?):', e.message)
    }
    return
  }
  const apiFromFrontend = path.resolve(process.cwd(), '..', 'api')
  const apiFromRoot = path.resolve(process.cwd(), 'api')
  const apiDir = fs.existsSync(path.join(apiFromFrontend, 'artisan')) ? apiFromFrontend : apiFromRoot
  if (!fs.existsSync(path.join(apiDir, 'artisan'))) {
    console.warn('[e2e] API directory not found; skip cleanup. Run manually: php artisan e2e:cleanup-users in api/')
    return
  }
  try {
    execSync('php artisan e2e:cleanup-users', { cwd: apiDir, stdio: 'pipe', encoding: 'utf8' })
  } catch (e) {
    console.warn('[e2e] Cleanup failed (run manually: php artisan e2e:cleanup-users in api/):', e.message)
  }
}

test.describe('Email delivery (real API + Mailhog)', () => {
  test.setTimeout(90000)
  test.afterAll(runE2eCleanup)
  test.describe.serial('', () => {
    test('register sends verification email to Mailhog', async ({ page, request }) => {
      const reachable = await checkEmailDeliveryReachable(request)
      test.skip(!reachable, 'API or Mailhog not reachable; start API and Mailhog to run this test.')

      const bodyBefore = await (await request.get(`${MAILHOG_URL}/api/v1/messages`)).json()
      const countBefore = getMessageCount(bodyBefore)

      const user = await createE2eUser(request, { emailPrefix: 'e2e-register', name: 'E2E Register User' })
      const { email } = user

      let countAfter = countBefore
      let messages = []
      for (let i = 0; i < 15; i++) {
        await new Promise((r) => setTimeout(r, 400))
        const res = await request.get(`${MAILHOG_URL}/api/v1/messages`)
        const body = await res.json()
        countAfter = getMessageCount(body)
        messages = getMessagesList(body)
        if (countAfter > countBefore) break
      }
      expect(
        countAfter,
        `Mailhog should have received the verification email (had ${countBefore}, got ${countAfter}).`
      ).toBeGreaterThan(countBefore)

      const found = messages.find((m) => messageIsTo(m, email))
      let isToOurEmail = !!found
      if (!isToOurEmail && messages.length > 0 && messages[0]?.ID) {
        for (const m of messages.slice(0, 5)) {
          if (!m?.ID) continue
          const fullRes = await request.get(`${MAILHOG_URL}/api/v1/messages/${m.ID}`)
          const full = await fullRes.json()
          if (messageIsTo(full, email)) {
            isToOurEmail = true
            break
          }
        }
      }
      expect(isToOurEmail, `Mailhog should have a message to ${email}.`).toBeTruthy()
    })

    test('resend link sends verification email to Mailhog', async ({ page, request }) => {
      const reachable = await checkEmailDeliveryReachable(request)
      test.skip(!reachable, 'API or Mailhog not reachable; start API and Mailhog to run this test.')

      await new Promise((r) => setTimeout(r, 2500))

      const user = await createE2eUser(request, { emailPrefix: 'e2e-resend', name: 'E2E Resend User' })
      const { email } = user

      const bodyAfterRegister = await (await request.get(`${MAILHOG_URL}/api/v1/messages`)).json()
      const countAfterRegister = getMessageCount(bodyAfterRegister)
      expect(countAfterRegister).toBeGreaterThanOrEqual(1)

      await page.goto(`/verify-email?email=${encodeURIComponent(email)}`)
      await expect(page.getByRole('button', { name: 'Resend link' })).toBeVisible()

      // Wait for resend API to succeed (ensures CORS / API is reachable from the app origin)
      const resendResponsePromise = page.waitForResponse(
        (res) => res.url().includes('/api/email/resend') && res.request().method() === 'POST',
        { timeout: 15000 }
      )
      await page.getByRole('button', { name: 'Resend link' }).click()
      const resendResponse = await resendResponsePromise
      expect(
        resendResponse.ok(),
        `Resend request failed (${resendResponse.status()}). If 0 or CORS error, add your app origin (e.g. http://localhost:8082) to API config/cors.php allowed_origins.`
      ).toBeTruthy()

      await Promise.race([
        page.getByRole('status').waitFor({ state: 'visible', timeout: 10000 }),
        page.getByRole('alert').filter({ hasText: /email|link|unverified/i }).waitFor({ state: 'visible', timeout: 10000 }),
        page.getByRole('button', { name: /Resend in \d+s/ }).waitFor({ state: 'visible', timeout: 10000 }),
      ]).catch(() => {})
      await page.waitForTimeout(2000)

      let countAfterResend = countAfterRegister
      for (let i = 0; i < 25; i++) {
        await page.waitForTimeout(500)
        const res = await request.get(`${MAILHOG_URL}/api/v1/messages`)
        const body = await res.json()
        countAfterResend = getMessageCount(body)
        if (countAfterResend > countAfterRegister) break
      }
      expect(
        countAfterResend,
        `Mailhog should have received one more email after resend (had ${countAfterRegister}, got ${countAfterResend}).`
      ).toBeGreaterThan(countAfterRegister)
    })

    test('forgot password sends reset email to Mailhog', async ({ page, request }) => {
      const reachable = await checkEmailDeliveryReachable(request)
      test.skip(!reachable, 'API or Mailhog not reachable; start API and Mailhog to run this test.')

      await new Promise((r) => setTimeout(r, 2500))

      const user = await createE2eUser(request, { emailPrefix: 'e2e-forgot', name: 'E2E Forgot User' })
      const { email } = user

      const bodyBefore = await (await request.get(`${MAILHOG_URL}/api/v1/messages`)).json()
      const countBefore = getMessageCount(bodyBefore)

      const forgotRes = await request.post(API_FORGOT_PASSWORD, { data: { email } })
      const forgotBody = await forgotRes.text()
      expect(
        forgotRes.ok(),
        `Forgot password failed (${forgotRes.status()}): ${forgotBody || 'no body'}`
      ).toBeTruthy()

      let countAfter = countBefore
      let messages = []
      for (let i = 0; i < 15; i++) {
        await new Promise((r) => setTimeout(r, 400))
        const res = await request.get(`${MAILHOG_URL}/api/v1/messages`)
        const body = await res.json()
        countAfter = getMessageCount(body)
        messages = getMessagesList(body)
        if (countAfter > countBefore) break
      }
      expect(
        countAfter,
        `Mailhog should have received the reset email (had ${countBefore}, got ${countAfter}).`
      ).toBeGreaterThan(countBefore)

      const found = messages.find((m) => messageIsTo(m, email))
      let isToOurEmail = !!found
      if (!isToOurEmail && messages.length > 0 && messages[0]?.ID) {
        for (const m of messages.slice(0, 5)) {
          if (!m?.ID) continue
          const fullRes = await request.get(`${MAILHOG_URL}/api/v1/messages/${m.ID}`)
          const full = await fullRes.json()
          if (messageIsTo(full, email)) {
            isToOurEmail = true
            break
          }
        }
      }
      expect(isToOurEmail, `Mailhog should have a message to ${email}.`).toBeTruthy()
    })
  })
})
