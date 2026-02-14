// @ts-check
/**
 * E2E tests for Profile & Settings (name/email update, change password).
 * Requires authenticated user; API calls are mocked.
 */
const { test, expect } = require('@playwright/test')

const MOCK_VERIFIED_USER = {
  id: 1,
  name: 'Test Owner',
  email: 'verified@example.com',
  email_verified_at: '2025-01-01T00:00:00.000000Z',
}

const MOCK_TOKEN = 'mock-sanctum-token'

async function loginAsVerifiedUser(page) {
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
}

test.describe('Profile & Settings', () => {
  test('unauthenticated visit to /app/profile redirects to login', async ({ page }) => {
    await page.goto('/app/profile')
    await expect(page).toHaveURL(/\/login/)
    await expect(page).toHaveURL(/\?redirect=/)
  })

  test('profile page shows heading and both forms when logged in', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.goto('/app/profile')

    await expect(page).toHaveURL(/\/app\/profile/)
    await expect(page.getByRole('heading', { name: 'Profile & Settings' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Profile', exact: true })).toBeVisible()
    await expect(page.getByLabel('Name')).toBeVisible()
    await expect(page.getByLabel('Email')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Save profile' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Change password' })).toBeVisible()
    await expect(page.getByLabel('Current password')).toBeVisible()
    await expect(page.getByLabel('New password', { exact: true })).toBeVisible()
    await expect(page.getByLabel('Confirm new password')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Update password' })).toBeVisible()
  })

  test('can navigate to profile from app sidebar', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.getByRole('link', { name: /profile & settings/i }).click()
    await expect(page).toHaveURL(/\/app\/profile/)
    await expect(page.getByRole('heading', { name: 'Profile & Settings' })).toBeVisible()
  })

  test('saving profile with updated name shows success', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.route('**/api/user', (route) => {
      if (route.request().method() === 'GET') {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ user: MOCK_VERIFIED_USER }),
        })
      }
      if (route.request().method() === 'PATCH') {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Profile updated.',
            user: { ...MOCK_VERIFIED_USER, name: 'Updated Name' },
          }),
        })
      }
      return route.continue()
    })

    await page.goto('/app/profile')
    await page.getByLabel('Name').fill('Updated Name')
    await page.getByRole('button', { name: 'Save profile' }).click()

    await expect(page.getByText(/profile updated|saved/i)).toBeVisible({ timeout: 5000 })
  })

  test('profile client-side validation: invalid email shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.goto('/app/profile')
    await page.getByLabel('Email').fill('not-an-email')
    await page.getByRole('button', { name: 'Save profile' }).click()

    await expect(page.getByText('Please enter a valid email address.')).toBeVisible()
  })

  test('profile client-side validation: name too long shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.goto('/app/profile')
    await page.getByLabel('Name').fill('a'.repeat(256))
    await page.getByRole('button', { name: 'Save profile' }).click()

    await expect(page.getByText('Name must be at most 255 characters.')).toBeVisible()
  })

  test('profile API validation error shows message', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.route('**/api/user', (route) => {
      if (route.request().method() === 'GET') {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ user: MOCK_VERIFIED_USER }),
        })
      }
      if (route.request().method() === 'PATCH') {
        return route.fulfill({
          status: 422,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'The email has already been taken.',
            errors: { email: ['The email has already been taken.'] },
          }),
        })
      }
      return route.continue()
    })

    await page.goto('/app/profile')
    await page.getByLabel('Email').fill('taken@example.com')
    await page.getByRole('button', { name: 'Save profile' }).click()

    await expect(page.getByText(/already been taken|failed to update profile/i)).toBeVisible({ timeout: 5000 })
  })

  test('change password client-side: empty current password shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.goto('/app/profile')
    await page.getByLabel('New password', { exact: true }).fill('NewPass123')
    await page.getByLabel('Confirm new password').fill('NewPass123')
    await page.getByRole('button', { name: 'Update password' }).click()

    await expect(page.getByText('Please enter your current password.')).toBeVisible()
  })

  test('change password client-side: new password too short shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.goto('/app/profile')
    await page.getByLabel('Current password').fill('password123')
    await page.getByLabel('New password', { exact: true }).fill('ab1')
    await page.getByLabel('Confirm new password').fill('ab1')
    await page.getByRole('button', { name: 'Update password' }).click()

    await expect(page.getByText('Password must be at least 8 characters.')).toBeVisible()
  })

  test('change password client-side: new password without letter shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.goto('/app/profile')
    await page.getByLabel('Current password').fill('password123')
    await page.getByLabel('New password', { exact: true }).fill('12345678')
    await page.getByLabel('Confirm new password').fill('12345678')
    await page.getByRole('button', { name: 'Update password' }).click()

    await expect(page.getByText('Password must include at least one letter.')).toBeVisible()
  })

  test('change password client-side: new password without number shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.goto('/app/profile')
    await page.getByLabel('Current password').fill('password123')
    await page.getByLabel('New password', { exact: true }).fill('abcdefgh')
    await page.getByLabel('Confirm new password').fill('abcdefgh')
    await page.getByRole('button', { name: 'Update password' }).click()

    await expect(page.getByText('Password must include at least one number.')).toBeVisible()
  })

  test('change password client-side: passwords do not match shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.goto('/app/profile')
    await page.getByLabel('Current password').fill('password123')
    await page.getByLabel('New password', { exact: true }).fill('NewPass123')
    await page.getByLabel('Confirm new password').fill('OtherPass456')
    await page.getByRole('button', { name: 'Update password' }).click()

    await expect(page.getByText('Passwords do not match.')).toBeVisible()
  })

  test('change password API error: wrong current password shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.route('**/api/profile/password', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The current password is incorrect.',
          errors: { current_password: ['The current password is incorrect.'] },
        }),
      })
    })

    await page.goto('/app/profile')
    await page.getByLabel('Current password').fill('wrongpass')
    await page.getByLabel('New password', { exact: true }).fill('NewPass123')
    await page.getByLabel('Confirm new password').fill('NewPass123')
    await page.getByRole('button', { name: 'Update password' }).click()

    await expect(page.getByText(/current password is incorrect|failed to update password/i)).toBeVisible({ timeout: 5000 })
  })

  test('change password success clears form and shows success', async ({ page }) => {
    await loginAsVerifiedUser(page)
    await page.route('**/api/profile/password', (route) => {
      if (route.request().method() !== 'POST') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Password updated successfully.' }),
      })
    })

    await page.goto('/app/profile')
    await page.getByLabel('Current password').fill('password123')
    await page.getByLabel('New password', { exact: true }).fill('NewPass123')
    await page.getByLabel('Confirm new password').fill('NewPass123')
    await page.getByRole('button', { name: 'Update password' }).click()

    await expect(page.getByText(/password updated|updated successfully/i)).toBeVisible({ timeout: 5000 })
    await expect(page.getByLabel('Current password')).toHaveValue('')
    await expect(page.getByLabel('New password', { exact: true })).toHaveValue('')
    await expect(page.getByLabel('Confirm new password')).toHaveValue('')
  })
})
