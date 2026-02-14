// @ts-check
/**
 * E2E tests for authentication (email/password only; SSO is out of scope).
 * API calls are mocked so tests can run without the backend.
 */
const { test, expect } = require('@playwright/test')

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

    await expect(page.getByRole('alert')).toContainText(/credentials are incorrect|sign in failed/i)
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

    await expect(page.getByRole('alert')).toContainText(/enter your name/i)
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
    await expect(page.getByRole('heading', { name: /check your email/i })).toBeVisible()
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
    await expect(page.getByRole('heading', { name: /check your email/i })).toBeVisible()
    await expect(page.getByRole('button', { name: /resend link/i })).toBeVisible()
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
