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
const { RegisterPage } = require('./pages/RegisterPage.cjs')
const { VerifyEmailPage } = require('./pages/VerifyEmailPage.cjs')
const { EmailVerifyConfirmPage } = require('./pages/EmailVerifyConfirmPage.cjs')
const { EmailVerifyNewPage } = require('./pages/EmailVerifyNewPage.cjs')
const { LoginPage } = require('./pages/LoginPage.cjs')

const REFRESH_COOKIE_NAME = 'rms_refresh'

const MOCK_VERIFIED_USER = {
  uuid: 'usr-owner-001',
  name: 'Test Owner',
  email: 'verified@example.com',
  email_verified_at: '2025-01-01T00:00:00.000000Z',
  pending_email: null,
  is_paid: false,
  is_superadmin: false,
  is_active: true,
}

const MOCK_TOKEN = 'mock-sanctum-token'

const LEGACY_AUTH_STORAGE_KEYS = [
  'rms-auth',
  'rms-auth-token',
  'rms-user-id',
  'rms-user-name',
  'rms-user-email',
  'rms-user-verified',
  'rms-user-pending-email',
  'rms-user-is-paid',
  'rms-user-is-superadmin',
  'rms-user-is-active',
]

function makeRefreshSetCookie(value) {
  // Minimal cookie string. Domain omitted so it defaults to the response host.
  return `${REFRESH_COOKIE_NAME}=${value}; Path=/; HttpOnly; SameSite=Lax`
}

function makeRefreshClearCookie() {
  return `${REFRESH_COOKIE_NAME}=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax`
}

async function expectNoLegacyAuthInStorage(page) {
  const values = await page.evaluate((keys) => {
    const out = {}
    for (const k of keys) out[k] = localStorage.getItem(k)
    return out
  }, LEGACY_AUTH_STORAGE_KEYS)
  for (const [k, v] of Object.entries(values)) {
    expect(v, `localStorage key "${k}" should not be set`).toBeNull()
  }
}

async function expectRefreshCookieState(page, { expectedValue } = {}) {
  const cookies = await page.context().cookies()
  const cookie = cookies.find((c) => c.name === REFRESH_COOKIE_NAME)
  expect(cookie, `${REFRESH_COOKIE_NAME} cookie should be present (HttpOnly refresh cookie).`).toBeTruthy()
  if (expectedValue !== undefined) expect(cookie.value).toBe(expectedValue)
  // HttpOnly cookie should not be readable by app JS.
  const docCookie = await page.evaluate(() => document.cookie)
  expect(docCookie).not.toContain(`${REFRESH_COOKIE_NAME}=`)
}

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
    await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible({ timeout: 15000 })
  })

  test('Create one link goes to /register', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('link', { name: /create one/i }).click()
    await expect(page).toHaveURL(/\/register/)
    await expect(page.getByTestId('register-heading')).toBeVisible({ timeout: 15000 })
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

  test('unverified email (403) redirects to verify-email with email query', async ({ page }) => {
    await page.route('**/api/login', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 403,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Your email address is not verified.' }),
      })
    })

    const loginPage = new LoginPage(page)
    await loginPage.goto()
    await loginPage.login('unverified@example.com', 'password123')

    const verifyEmailPage = new VerifyEmailPage(page)
    await verifyEmailPage.expectVerifyEmailUrl('unverified@example.com')
    await verifyEmailPage.expectCheckYourEmailHeadingVisible()
    await verifyEmailPage.expectEmailDisplayed(/unverified@example\.com/)
  })

  test('successful login redirects to /app and shows Sign out', async ({ page }) => {
    await page.route('**/api/login', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: {
          'Set-Cookie': makeRefreshSetCookie('rt-1'),
        },
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

    // Access token is in memory only (no legacy localStorage tokens).
    await expectNoLegacyAuthInStorage(page)
    // Refresh token is an HttpOnly cookie (not readable by JS, but visible to the browser).
    await expectRefreshCookieState(page, { expectedValue: 'rt-1' })
  })

  test('Forgot password link goes to /forgot-password', async ({ page }) => {
    await page.goto('/login')
    await page.getByRole('link', { name: /forgot password/i }).click()
    await expect(page).toHaveURL(/\/forgot-password/)
    await expect(page.getByRole('heading', { name: /forgot password/i })).toBeVisible()
  })
})

/** Mock GET /api/legal/terms and GET /api/legal/privacy for legal modal tests. */
function mockLegalApi(page, termsContent = '<p>Terms of Service content.</p>', privacyContent = '<p>Privacy Policy content.</p>') {
  page.route(/\/api\/legal\/terms(\?.*)?$/, (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { content: termsContent } }),
    })
  })
  page.route(/\/api\/legal\/privacy(\?.*)?$/, (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { content: privacyContent } }),
    })
  })
}

/** Mock GET /api/legal/terms and GET /api/legal/privacy with locale-aware content (query ?locale=es etc.). */
function mockLegalApiWithLocale(page, contentByLocale = {}) {
  const defaultTerms = '<p>Terms of Service content.</p>'
  const defaultPrivacy = '<p>Privacy Policy content.</p>'
  const getLocale = (url) => {
    try {
      const u = new URL(url)
      return (u.searchParams.get('locale') || 'en').toLowerCase()
    } catch {
      return 'en'
    }
  }
  page.route(/\/api\/legal\/terms(\?.*)?$/, (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    const loc = getLocale(route.request().url())
    const content = contentByLocale[loc]?.terms ?? contentByLocale.en?.terms ?? defaultTerms
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { content } }),
    })
  })
  page.route(/\/api\/legal\/privacy(\?.*)?$/, (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    const loc = getLocale(route.request().url())
    const content = contentByLocale[loc]?.privacy ?? contentByLocale.en?.privacy ?? defaultPrivacy
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { content } }),
    })
  })
}

test.describe('Register', () => {
  test('shows single-step form with all fields', async ({ page }) => {
    const registerPage = new RegisterPage(page)
    await registerPage.goto()
    await registerPage.expectRegistrationFormVisible()
  })

  test('validation shows error for empty name', async ({ page }) => {
    const registerPage = new RegisterPage(page)
    await registerPage.goto()
    await registerPage.fillEmail('jane@example.com')
    await registerPage.fillPassword('Password123')
    await registerPage.fillConfirmPassword('Password123')
    await registerPage.checkTerms()
    await registerPage.submit()
    await registerPage.expectValidationError('Please enter your name.')
  })

  test('full registration redirects to verify-email', async ({ page }) => {
    await page.route('**/api/register', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Registered. Please verify your email using the link we sent you.',
          user: { uuid: 'usr-jane-002', name: 'Jane', email: 'jane@example.com', email_verified_at: null },
        }),
      })
    })

    const registerPage = new RegisterPage(page)
    await registerPage.goto()
    await registerPage.fillAndSubmit({
      name: 'Jane Doe',
      email: 'jane@example.com',
      password: 'Password123',
    })
    await registerPage.expectRedirectedToVerifyEmail()
  })

  test.describe('Legal modals (Terms of Service, Privacy Policy)', () => {
    test('Terms of Service button opens modal with content', async ({ page }) => {
      mockLegalApi(page)
      const registerPage = new RegisterPage(page)
      await registerPage.goto()
      await registerPage.clickTermsOfService()
      await registerPage.expectLegalModalVisible()
      await registerPage.expectLegalModalBodyContains('Terms of Service content.', 15000)
      await registerPage.closeLegalModal()
      await registerPage.expectLegalModalClosed()
    })

    test('Privacy Policy button opens modal with content', async ({ page }) => {
      mockLegalApi(page)
      const registerPage = new RegisterPage(page)
      await registerPage.goto()
      await registerPage.clickPrivacyPolicy()
      await registerPage.expectLegalModalVisible()
      await registerPage.expectLegalModalBodyContains('Privacy Policy content.', 15000)
      await registerPage.closeLegalModal()
      await registerPage.expectLegalModalClosed()
    })

    test('modal closes via close button', async ({ page }) => {
      mockLegalApi(page)
      const registerPage = new RegisterPage(page)
      await registerPage.goto()
      await registerPage.clickTermsOfService()
      await registerPage.expectLegalModalVisible()
      await registerPage.closeLegalModal()
      await registerPage.expectLegalModalClosed()
    })

    test('modal closes via overlay click', async ({ page }) => {
      mockLegalApi(page)
      const registerPage = new RegisterPage(page)
      await registerPage.goto()
      await registerPage.clickPrivacyPolicy()
      await registerPage.expectLegalModalVisible()
      await registerPage.closeLegalModalByOverlay()
      await registerPage.expectLegalModalClosed()
    })

    test('Terms modal requests and shows content for app locale (Spanish)', async ({ page }) => {
      const spanishTerms = '<p>Términos de servicio en español.</p>'
      mockLegalApiWithLocale(page, {
        en: { terms: '<p>Terms of Service content.</p>', privacy: '<p>Privacy Policy content.</p>' },
        es: { terms: spanishTerms, privacy: '<p>Política de privacidad.</p>' },
      })
      await page.addInitScript(() => {
        localStorage.setItem('rms_app_locale', 'es')
      })
      const registerPage = new RegisterPage(page)
      await registerPage.goto()
      await registerPage.clickTermsOfService()
      await registerPage.expectLegalModalVisible()
      await registerPage.expectLegalModalBodyContains('Términos de servicio en español', 15000)
      await registerPage.closeLegalModal()
      await registerPage.expectLegalModalClosed()
    })
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
    await page.getByLabel(/email address/i).fill('')
    await page.getByRole('button', { name: /send reset link/i }).click()
    // i18n: verify.emailRequired (en / es / ar). Error is shown in role=alert under the field.
    await expect(page.getByRole('alert').filter({ hasText: /please enter your email|introduce tu correo|يرجى إدخال بريدك/i })).toBeVisible()
  })

  test('invalid email shows validation error', async ({ page }) => {
    await page.goto('/forgot-password')
    await page.getByLabel(/email address/i).fill('not-an-email')
    await page.getByRole('button', { name: /send reset link/i }).click()
    // i18n: verify.emailInvalid (en / es / ar). Error is shown in role=alert under the field.
    await expect(page.getByRole('alert').filter({ hasText: /please enter a valid email|introduce un correo.*válido|يرجى إدخال بريد إلكتروني صحيح/i })).toBeVisible()
  })

  test('Back to sign in goes to /login', async ({ page }) => {
    await page.goto('/forgot-password')
    // Link text is "Back sign in" (i18n back + signIn) or "Back to sign in" (success state)
    await page.getByRole('link', { name: /back.*sign in/i }).click()
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
  test('land on verify-email page and see resend', async ({ page }) => {
    const verifyEmailPage = new VerifyEmailPage(page)
    await verifyEmailPage.goto({ email: 'user@example.com' })
    await verifyEmailPage.expectCheckYourEmailHeadingVisible()
    await verifyEmailPage.expectResendVisible()
  })

  test('Back to sign in link goes to login', async ({ page }) => {
    const verifyEmailPage = new VerifyEmailPage(page)
    await verifyEmailPage.goto({ email: 'user@example.com' })
    await verifyEmailPage.expectBackToSignInLinkVisible()
    await verifyEmailPage.clickBackToSignIn()
    await expect(page).toHaveURL(/\/login/)
  })

  test('Go to sign in CTA is visible', async ({ page }) => {
    const verifyEmailPage = new VerifyEmailPage(page)
    await verifyEmailPage.goto({ email: 'user@example.com' })
    await verifyEmailPage.expectGoToSignInButtonVisible()
  })
})

test.describe('Email verify confirm (/email/verify)', () => {
  const signedParams = { uuid: 'usr-001', hash: 'abc123', expires: '9999999999', signature: 'sig' }

  test('shows success card when API returns 200', async ({ page }) => {
    await page.route(/\/api\/email\/verify\/[^/]+\/[^/]+/, (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Email verified successfully. You can now log in.',
          user: { uuid: 'usr-001', name: 'Test', email: 'test@example.com', email_verified_at: '2025-01-01T00:00:00Z' },
        }),
      })
    })

    const confirmPage = new EmailVerifyConfirmPage(page)
    await confirmPage.goto(signedParams)
    await confirmPage.waitForResult(15000)
    await confirmPage.expectSuccessCardVisible()
  })

  test('shows failure card when API returns 422', async ({ page }) => {
    await page.route(/\/api\/email\/verify\/[^/]+\/[^/]+/, (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Invalid or expired link.', errors: { email: ['Invalid or expired link.'] } }),
      })
    })

    const confirmPage = new EmailVerifyConfirmPage(page)
    await confirmPage.goto(signedParams)
    await confirmPage.waitForResult(15000)
    await confirmPage.expectFailureCardVisible()
  })

  test('shows failure card when link params are missing', async ({ page }) => {
    await page.goto('/email/verify')
    const confirmPage = new EmailVerifyConfirmPage(page)
    await confirmPage.waitForResult(5000)
    await confirmPage.expectFailureCardVisible()
  })
})

test.describe('Email verify new (/email/verify-new)', () => {
  const signedParams = { uuid: 'usr-002', hash: 'def456', expires: '9999999999', signature: 'sig2' }

  test('shows success card when API returns 200', async ({ page }) => {
    await page.route(/\/api\/email\/verify-new\/[^/]+\/[^/]+/, (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Your email has been updated and verified.',
          user: { uuid: 'usr-002', name: 'Test', email: 'new@example.com', email_verified_at: '2025-01-01T00:00:00Z' },
        }),
      })
    })

    const newPage = new EmailVerifyNewPage(page)
    await newPage.goto(signedParams)
    await newPage.waitForResult(15000)
    await newPage.expectSuccessCardVisible()
  })

  test('shows failure card when API returns 422', async ({ page }) => {
    await page.route(/\/api\/email\/verify-new\/[^/]+\/[^/]+/, (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Invalid or expired link.', errors: { email: ['Invalid or expired link.'] } }),
      })
    })

    const newPage = new EmailVerifyNewPage(page)
    await newPage.goto(signedParams)
    await newPage.waitForResult(15000)
    await newPage.expectFailureCardVisible()
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
        headers: {
          'Set-Cookie': makeRefreshSetCookie('rt-1'),
        },
        body: JSON.stringify({
          message: 'Logged in successfully.',
          user: MOCK_VERIFIED_USER,
          token: MOCK_TOKEN,
          token_type: 'Bearer',
        }),
      })
    })
    // Some app flows call GET /api/user; keep mocked for stability.
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

    // After a full navigation/reload, the app restores auth by POST /api/auth/refresh (cookie-based).
    // Mock refresh so the app becomes authenticated again on the next page load.
    await page.route('**/api/auth/refresh', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: {
          'Set-Cookie': makeRefreshSetCookie('rt-2'),
        },
        body: JSON.stringify({
          message: 'Token refreshed successfully.',
          user: MOCK_VERIFIED_USER,
          token: MOCK_TOKEN,
          token_type: 'Bearer',
        }),
      })
    })

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
        headers: {
          'Set-Cookie': makeRefreshSetCookie('rt-1'),
        },
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

test.describe('Refresh token auth (cookie-based)', () => {
  test('login -> /app -> reload -> refresh is called and user stays authenticated (no localStorage token)', async ({ page }) => {
    await page.route('**/api/login', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: {
          'Set-Cookie': makeRefreshSetCookie('rt-1'),
        },
        body: JSON.stringify({
          message: 'Logged in successfully.',
          user: MOCK_VERIFIED_USER,
          token: MOCK_TOKEN,
          token_type: 'Bearer',
        }),
      })
    })

    await page.route('**/api/auth/refresh', (route) => {
      if (route.request().method() !== 'POST') return route.continue()

      // Important: the app calls refresh-on-load even on guest routes (like /login).
      // Only return 200 when the browser has the refresh cookie set (after login).
      const cookieHeader = route.request().headers()['cookie'] || ''
      const hasRefreshCookie = cookieHeader.includes(`${REFRESH_COOKIE_NAME}=rt-1`)
      if (!hasRefreshCookie) {
        return route.fulfill({
          status: 401,
          contentType: 'application/json',
          headers: {
            'Set-Cookie': makeRefreshClearCookie(),
          },
          body: JSON.stringify({ message: 'Invalid or expired refresh token.' }),
        })
      }

      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: {
          // refresh rotation
          'Set-Cookie': makeRefreshSetCookie('rt-2'),
        },
        body: JSON.stringify({
          message: 'Token refreshed successfully.',
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
    await expectNoLegacyAuthInStorage(page)
    await expectRefreshCookieState(page, { expectedValue: 'rt-1' })

    const refreshRequestPromise = page.waitForRequest(
      (req) => req.method() === 'POST' && req.url().includes('/api/auth/refresh'),
      { timeout: 10000 }
    )
    await page.reload()
    await refreshRequestPromise

    await expect(page).toHaveURL(/\/app/)
    await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible()
    await expect(page.getByText(/welcome,/i)).toBeVisible()
    await expectNoLegacyAuthInStorage(page)
    await expectRefreshCookieState(page, { expectedValue: 'rt-2' })
  })

  test('refresh 401 -> treated as logged out -> protected route redirects to login', async ({ page }) => {
    // Simulate an existing refresh cookie from a prior session.
    await page.context().addCookies([
      {
        name: REFRESH_COOKIE_NAME,
        value: 'rt-stale',
        domain: 'localhost',
        path: '/',
        httpOnly: true,
        sameSite: 'Lax',
      },
    ])

    await page.route('**/api/auth/refresh', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 401,
        contentType: 'application/json',
        headers: {
          // backend clears refresh cookie on 401/403 to prevent loops
          'Set-Cookie': makeRefreshClearCookie(),
        },
        body: JSON.stringify({ message: 'Invalid or expired refresh token.' }),
      })
    })

    await page.goto('/app')
    await expect(page).toHaveURL(/\/login/)
    await expect(page).toHaveURL(/\?redirect=/)
    await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible()
    await expectNoLegacyAuthInStorage(page)
  })
})

// ---- Email delivery (real API + Mailhog; skipped if unreachable) ----
const MAILHOG_URL = process.env.MAILHOG_URL || 'http://localhost:8025'
const API_ORIGIN = (process.env.E2E_API_BASE || process.env.VITE_PROXY_TARGET || '').replace(/\/$/, '')
const API_HEALTH = API_ORIGIN ? `${API_ORIGIN}/api/health` : '/api/health'
const API_REGISTER = API_ORIGIN ? `${API_ORIGIN}/api/register` : '/api/register'
const API_FORGOT_PASSWORD = API_ORIGIN ? `${API_ORIGIN}/api/forgot-password` : '/api/forgot-password'
const RUN_EMAIL_DELIVERY = process.env.E2E_EMAIL_DELIVERY === '1'

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
  test.skip(!RUN_EMAIL_DELIVERY, 'Set E2E_EMAIL_DELIVERY=1 to run real API + Mailhog email delivery tests.')
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

      const verifyEmailPage = new VerifyEmailPage(page)
      await verifyEmailPage.goto({ email })
      await verifyEmailPage.expectResendVisible()

      // Wait for resend API to succeed (ensures CORS / API is reachable from the app origin)
      const resendResponsePromise = page.waitForResponse(
        (res) => res.url().includes('/api/email/resend') && res.request().method() === 'POST',
        { timeout: 15000 }
      )
      await verifyEmailPage.clickResend()
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
