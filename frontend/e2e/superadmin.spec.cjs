// @ts-check
/**
 * E2E tests for the Superadmin module: access control, superadmin nav, dashboard stats, users list and toggles.
 * All API calls are mocked. No element querying in test cases — only Page Object and helper methods.
 */
const { test, expect } = require('@playwright/test')
const { LoginPage } = require('./pages/LoginPage.cjs')
const { SuperadminAppPage } = require('./pages/SuperadminAppPage.cjs')
const { SuperadminDashboardPage } = require('./pages/SuperadminDashboardPage.cjs')
const { SuperadminUsersPage } = require('./pages/SuperadminUsersPage.cjs')
const { SuperadminRestaurantsPage } = require('./pages/SuperadminRestaurantsPage.cjs')

const REFRESH_COOKIE_NAME = 'rms_refresh'
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
  return `${REFRESH_COOKIE_NAME}=${value}; Path=/; HttpOnly; SameSite=Lax`
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

const MOCK_REGULAR_USER = {
  uuid: 'usr-regular-111',
  name: 'Test Owner',
  email: 'owner@example.com',
  email_verified_at: '2025-01-01T00:00:00.000000Z',
  is_paid: false,
  is_superadmin: false,
  is_active: true,
}

const MOCK_SUPERADMIN_USER = {
  uuid: 'usr-superadmin-001',
  name: 'Super Admin',
  email: 'superadmin@example.com',
  email_verified_at: '2025-01-01T00:00:00.000000Z',
  is_paid: false,
  is_superadmin: true,
  is_active: true,
}

/** Set up mocks for login and GET /api/user with the given user payload. */
function mockLoginAndUser(page, userPayload) {
  page.route('**/api/login', (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      headers: {
        'Set-Cookie': makeRefreshSetCookie('rt-1'),
      },
      body: JSON.stringify({
        message: 'Logged in successfully.',
        user: userPayload,
        token: MOCK_TOKEN,
        token_type: 'Bearer',
      }),
    })
  })
  page.route('**/api/user', (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ user: userPayload }),
    })
  })
}

/** Log in as regular (owner) user; mocks and performs login. */
async function loginAsRegularUser(page) {
  mockLoginAndUser(page, MOCK_REGULAR_USER)
  const loginPage = new LoginPage(page)
  await loginPage.goto()
  await loginPage.login(MOCK_REGULAR_USER.email, 'password123')
  await page.waitForURL(/\/app(\/)?$/, { timeout: 10000 })
  await expectNoLegacyAuthInStorage(page)

  // Keep auth across page.goto() navigations by mocking refresh.
  await page.route('**/api/auth/refresh', (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      headers: {
        // refresh rotation
        'Set-Cookie': makeRefreshSetCookie('rt-2'),
      },
      body: JSON.stringify({
        message: 'Token refreshed successfully.',
        user: MOCK_REGULAR_USER,
        token: MOCK_TOKEN,
        token_type: 'Bearer',
      }),
    })
  })
}

/** Log in as superadmin; mocks and performs login. */
async function loginAsSuperadmin(page) {
  mockLoginAndUser(page, MOCK_SUPERADMIN_USER)
  const loginPage = new LoginPage(page)
  await loginPage.goto()
  await loginPage.login(MOCK_SUPERADMIN_USER.email, 'password123')
  await page.waitForURL(/\/app(\/)?$/, { timeout: 10000 })
  await expectNoLegacyAuthInStorage(page)

  // Keep auth across page.goto() navigations by mocking refresh.
  await page.route('**/api/auth/refresh', (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      headers: {
        // refresh rotation
        'Set-Cookie': makeRefreshSetCookie('rt-2'),
      },
      body: JSON.stringify({
        message: 'Token refreshed successfully.',
        user: MOCK_SUPERADMIN_USER,
        token: MOCK_TOKEN,
        token_type: 'Bearer',
      }),
    })
  })
}

test.describe('Superadmin access control', () => {
  test('regular user visiting /app/superadmin/users is redirected to /app', async ({ page }) => {
    mockLoginAndUser(page, MOCK_REGULAR_USER)
    const loginPage = new LoginPage(page)
    await loginPage.goto()
    await loginPage.login(MOCK_REGULAR_USER.email, 'password123')
    await expect(page).toHaveURL(/\/app(\/)?$/)

    const appPage = new SuperadminAppPage(page)
    await appPage.goto('/app/superadmin/users')
    await appPage.expectRedirectedToApp()
  })

  test('regular user visiting /app/superadmin/restaurants is redirected to /app', async ({ page }) => {
    mockLoginAndUser(page, MOCK_REGULAR_USER)
    const loginPage = new LoginPage(page)
    await loginPage.goto()
    await loginPage.login(MOCK_REGULAR_USER.email, 'password123')
    await expect(page).toHaveURL(/\/app(\/)?$/)

    const appPage = new SuperadminAppPage(page)
    await appPage.goto('/app/superadmin/restaurants')
    await appPage.expectRedirectedToApp()
  })

  test('superadmin can access /app/superadmin/users', async ({ page }) => {
    await loginAsSuperadmin(page)
    const appPage = new SuperadminAppPage(page)
    await appPage.goto('/app/superadmin/users')
    await appPage.expectUsersPageUrl()
    const usersPage = new SuperadminUsersPage(page)
    await usersPage.expectUsersHeadingVisible()
  })

  test('superadmin can access /app/superadmin/restaurants', async ({ page }) => {
    await loginAsSuperadmin(page)
    const appPage = new SuperadminAppPage(page)
    await appPage.goto('/app/superadmin/restaurants')
    await appPage.expectRestaurantsPageUrl()
    const restaurantsPage = new SuperadminRestaurantsPage(page)
    await restaurantsPage.expectRestaurantsHeadingVisible()
  })
})

test.describe('Superadmin login and nav', () => {
  test('sidenav shows Dashboard, Users, Restaurants, Profile & Settings and does not show Menu items or Feedbacks', async ({ page }) => {
    await loginAsSuperadmin(page)
    const appPage = new SuperadminAppPage(page)
    await appPage.expectDashboardLinkVisible()
    await appPage.expectUsersLinkVisible()
    await appPage.expectRestaurantsLinkVisible()
    await appPage.expectProfileLinkVisible()
    await appPage.expectMenuItemsLinkNotVisible()
    await appPage.expectFeedbacksLinkNotVisible()
  })
})

test.describe('Superadmin dashboard', () => {
  test('superadmin sees dashboard with stats (restaurants, users, paid users count)', async ({ page }) => {
    await loginAsSuperadmin(page)
    page.route('**/api/superadmin/stats', (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            restaurants_count: 5,
            users_count: 10,
            paid_users_count: 2,
          },
        }),
      })
    })
    await page.goto('/app')
    const dashboardPage = new SuperadminDashboardPage(page)
    await dashboardPage.expectDashboardVisible()
    await dashboardPage.expectHeadingVisible()
    await dashboardPage.expectStatsCards(5, 10, 2)
    await dashboardPage.expectWelcomeVisible()
  })
})

test.describe('Superadmin users list', () => {
  test('superadmin can open Users page and see user list', async ({ page }) => {
    await loginAsSuperadmin(page)
    page.route('**/api/superadmin/users', (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              uuid: 'usr-other-001',
              name: 'Other User',
              email: 'other@example.com',
              email_verified_at: '2025-01-01T00:00:00.000000Z',
              is_paid: false,
              is_active: true,
              is_superadmin: false,
            },
          ],
        }),
      })
    })
    const appPage = new SuperadminAppPage(page)
    await appPage.navigateToUsers()
    await appPage.expectUsersPageUrl()
    const usersPage = new SuperadminUsersPage(page)
    await usersPage.expectUserListVisible()
    await usersPage.expectUserRowWithEmailVisible('other@example.com')
  })

  test('superadmin can toggle paid for a user and see updated state', async ({ page }) => {
    await loginAsSuperadmin(page)
    const otherUserUuid = 'usr-other-002'
    let paidState = false
    page.route('**/api/superadmin/users', (route) => {
      if (route.request().method() === 'GET') {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            data: [
              {
                uuid: otherUserUuid,
                name: 'Paid Test User',
                email: 'paidtest@example.com',
                email_verified_at: '2025-01-01T00:00:00.000000Z',
                is_paid: paidState,
                is_active: true,
                is_superadmin: false,
              },
            ],
          }),
        })
      }
      return route.continue()
    })
    page.route(`**/api/superadmin/users/${otherUserUuid}`, (route) => {
      if (route.request().method() !== 'PATCH') return route.continue()
      paidState = true
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'User updated.',
          data: {
            uuid: otherUserUuid,
            name: 'Paid Test User',
            email: 'paidtest@example.com',
            email_verified_at: '2025-01-01T00:00:00.000000Z',
            is_paid: true,
            is_active: true,
            is_superadmin: false,
          },
        }),
      })
    })
    const appPage = new SuperadminAppPage(page)
    await appPage.navigateToUsers()
    const usersPage = new SuperadminUsersPage(page)
    await usersPage.expectUserListVisible()
    await usersPage.expectUserRowWithEmailVisible('paidtest@example.com')
    await usersPage.clickPaidToggleForUser('paidtest@example.com')
    await usersPage.expectUserRowShowsPaid('paidtest@example.com')
  })

  test('superadmin sees error when PATCH user returns 422', async ({ page }) => {
    await loginAsSuperadmin(page)
    const otherUserUuid = 'usr-other-003'
    page.route('**/api/superadmin/users', (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              uuid: otherUserUuid,
              name: 'Deactivate Test',
              email: 'deact@example.com',
              email_verified_at: '2025-01-01T00:00:00.000000Z',
              is_paid: false,
              is_active: true,
              is_superadmin: false,
            },
          ],
        }),
      })
    })
    page.route(`**/api/superadmin/users/${otherUserUuid}`, (route) => {
      if (route.request().method() !== 'PATCH') return route.continue()
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Validation failed.',
          errors: { is_active: ['Cannot change your own status.'] },
        }),
      })
    })
    const appPage = new SuperadminAppPage(page)
    await appPage.navigateToUsers()
    const usersPage = new SuperadminUsersPage(page)
    await usersPage.expectUserRowWithEmailVisible('deact@example.com')
    await usersPage.clickActiveToggleForUser('deact@example.com')
    await usersPage.expectAlertWithText(/cannot change your own|validation failed/i)
  })
})

test.describe('Superadmin restaurants list', () => {
  test('superadmin can open Restaurants page and see list', async ({ page }) => {
    await loginAsSuperadmin(page)
    page.route('**/api/superadmin/restaurants', (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              uuid: 'rstr-sa-001',
              name: 'First Restaurant',
              slug: 'first-restaurant',
              address: '1 Main St',
            },
          ],
        }),
      })
    })
    const appPage = new SuperadminAppPage(page)
    await appPage.navigateToRestaurants()
    await appPage.expectRestaurantsPageUrl()
    const restaurantsPage = new SuperadminRestaurantsPage(page)
    await restaurantsPage.expectRestaurantListVisible()
  })

  test('superadmin restaurants page shows empty state when no restaurants', async ({ page }) => {
    // Mock empty list before login so the route is in place before any navigation
    page.route(/\/api\/superadmin\/restaurants(\?.*)?$/, (route) => {
      if (route.request().method() !== 'GET') return route.continue()
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [] }),
      })
    })
    await loginAsSuperadmin(page)
    const appPage = new SuperadminAppPage(page)
    await appPage.navigateToRestaurants()
    await appPage.expectRestaurantsPageUrl()
    const restaurantsPage = new SuperadminRestaurantsPage(page)
    await restaurantsPage.expectRestaurantsHeadingVisible()
    await restaurantsPage.expectNoRestaurantsFound()
  })
})
