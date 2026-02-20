// @ts-check
/**
 * E2E tests for the Owner feedback (feature requests) module: owner submit/list and superadmin list/toggle.
 * All API calls are mocked. No selectors in test bodies — only Page Object and helper methods.
 */
const { test, expect } = require('@playwright/test')
const { LoginPage } = require('./pages/LoginPage.cjs')
const { OwnerFeedbackPage } = require('./pages/OwnerFeedbackPage.cjs')
const { SuperadminAppPage } = require('./pages/SuperadminAppPage.cjs')
const { SuperadminOwnerFeedbacksPage } = require('./pages/SuperadminOwnerFeedbacksPage.cjs')

const MOCK_TOKEN = 'mock-sanctum-token'

const MOCK_REGULAR_USER = {
  uuid: 'usr-owner-fb-001',
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

function mockLoginAndUser(page, userPayload) {
  page.route('**/api/login', (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
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

async function loginAsRegularUser(page) {
  mockLoginAndUser(page, MOCK_REGULAR_USER)
  const loginPage = new LoginPage(page)
  await loginPage.goto()
  await loginPage.login(MOCK_REGULAR_USER.email, 'password123')
  await page.waitForURL(/\/app(\/)?$/, { timeout: 10000 })
}

async function loginAsSuperadmin(page) {
  mockLoginAndUser(page, MOCK_SUPERADMIN_USER)
  const loginPage = new LoginPage(page)
  await loginPage.goto()
  await loginPage.login(MOCK_SUPERADMIN_USER.email, 'password123')
  await page.waitForURL(/\/app(\/)?$/, { timeout: 10000 })
}

/** Mock GET and POST /api/owner-feedback. GET returns myFeedbacks array (mutate and push from POST). */
function mockOwnerFeedbackApi(page, initialMyFeedbacks = []) {
  const myFeedbacks = initialMyFeedbacks.map((f) => ({ ...f }))

  page.route('**/api/owner-feedback**', (route) => {
    const url = route.request().url()
    const method = route.request().method()
    if (method === 'GET' && !url.includes('/api/owner-feedback?') && url.endsWith('/owner-feedback')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [...myFeedbacks] }),
      })
    }
    if (method === 'POST' && url.includes('/owner-feedback')) {
      const body = JSON.parse(route.request().postData() || '{}')
      const message = (body.message || '').trim()
      if (!message) {
        return route.fulfill({
          status: 422,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'The given data was invalid.',
            errors: { message: ['Message is required.'] },
          }),
        })
      }
      const created = {
        uuid: 'ofb-' + Date.now(),
        title: body.title || null,
        message,
        status: 'pending',
        created_at: new Date().toISOString(),
        submitter: { uuid: MOCK_REGULAR_USER.uuid, name: MOCK_REGULAR_USER.name },
        restaurant: null,
      }
      myFeedbacks.unshift(created)
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Feedback submitted.',
          data: created,
        }),
      })
    }
    return route.continue()
  })
}

/** Mock GET /api/superadmin/owner-feedbacks to return the given list. */
function mockSuperadminOwnerFeedbacksList(page, feedbacks) {
  page.route('**/api/superadmin/owner-feedbacks**', (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    const url = route.request().url()
    if (!url.includes('owner-feedbacks') || /\/owner-feedbacks\/[^/?#]+/.test(url)) return route.continue()
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: feedbacks }),
    })
  })
}

/** Mock PATCH /api/superadmin/owner-feedbacks/:uuid to toggle status and return updated item. */
function mockSuperadminOwnerFeedbackPatch(page, feedbacks) {
  const list = feedbacks.map((f) => ({ ...f }))
  page.route(/\/api\/superadmin\/owner-feedbacks\/[^/?#]+/, (route) => {
    if (route.request().method() !== 'PATCH') return route.continue()
    const uuid = route.request().url().match(/\/owner-feedbacks\/([^/]+)$/)?.[1]
    const body = JSON.parse(route.request().postData() || '{}')
    const idx = list.findIndex((f) => f.uuid === uuid)
    if (idx === -1) {
      return route.fulfill({
        status: 404,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Feedback not found.' }),
      })
    }
    const nextStatus = body.status ?? (list[idx].status === 'reviewed' ? 'pending' : 'reviewed')
    list[idx] = { ...list[idx], status: nextStatus }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        message: 'Feedback updated.',
        data: list[idx],
      }),
    })
  })
}

/** Mock GET /api/restaurants for owner (optional dropdown on owner-feedback page). */
function mockRestaurantList(page, restaurants = []) {
  page.route(/\/api\/restaurants(\?.*)?$/, (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: restaurants,
        meta: { current_page: 1, last_page: 1, per_page: 15, total: restaurants.length },
      }),
    })
  })
}

// --- Owner flow ---

test.describe('Owner feedback (owner)', () => {
  test('unauthenticated visit to /app/owner-feedback redirects to login', async ({ page }) => {
    await page.goto('/app/owner-feedback')
    await expect(page).toHaveURL(/\/login/)
    await expect(page).toHaveURL(/\?redirect=/)
  })

  test('owner can open Feature request page via nav', async ({ page }) => {
    await loginAsRegularUser(page)
    mockOwnerFeedbackApi(page, [])
    mockRestaurantList(page, [])
    const ownerFeedbackPage = new OwnerFeedbackPage(page)
    await ownerFeedbackPage.goToViaSidenav()
    await ownerFeedbackPage.expectPageVisible()
    await ownerFeedbackPage.expectMyRequestsSectionVisible()
  })

  test('owner can open Feature request page via route', async ({ page }) => {
    await loginAsRegularUser(page)
    mockOwnerFeedbackApi(page, [])
    mockRestaurantList(page, [])
    const ownerFeedbackPage = new OwnerFeedbackPage(page)
    await ownerFeedbackPage.goTo()
    await ownerFeedbackPage.expectPageVisible()
    await ownerFeedbackPage.expectEmptyMyRequests()
  })

  test('owner can submit feedback with message only and see success and list update', async ({
    page,
  }) => {
    await loginAsRegularUser(page)
    mockOwnerFeedbackApi(page, [])
    mockRestaurantList(page, [])
    const ownerFeedbackPage = new OwnerFeedbackPage(page)
    await ownerFeedbackPage.goTo()
    await ownerFeedbackPage.expectPageVisible()
    await ownerFeedbackPage.setMessage('I need dark mode for the public menu.')
    await ownerFeedbackPage.submitForm()
    await ownerFeedbackPage.expectToastSuccess('Feedback submitted')
    await ownerFeedbackPage.expectMyRequestWithMessageVisible('I need dark mode for the public menu.')
    await ownerFeedbackPage.expectMyRequestCount(1)
  })

  test('owner can submit feedback with message and title', async ({ page }) => {
    await loginAsRegularUser(page)
    mockOwnerFeedbackApi(page, [])
    mockRestaurantList(page, [])
    const ownerFeedbackPage = new OwnerFeedbackPage(page)
    await ownerFeedbackPage.goTo()
    await ownerFeedbackPage.setMessage('Add export to PDF for the menu.')
    await ownerFeedbackPage.setTitle('PDF export')
    await ownerFeedbackPage.submitForm()
    await ownerFeedbackPage.expectToastSuccess('Feedback submitted')
    await ownerFeedbackPage.expectMyRequestWithMessageVisible('PDF export')
    await ownerFeedbackPage.expectMyRequestWithMessageVisible('Add export to PDF')
  })

  test('owner submitting without message sees field error', async ({ page }) => {
    await loginAsRegularUser(page)
    mockOwnerFeedbackApi(page, [])
    mockRestaurantList(page, [])
    const ownerFeedbackPage = new OwnerFeedbackPage(page)
    await ownerFeedbackPage.goTo()
    await ownerFeedbackPage.expectPageVisible()
    await ownerFeedbackPage.submitForm()
    await ownerFeedbackPage.expectMessageFieldError('Message is required')
  })
})

// --- Superadmin flow ---

test.describe('Owner feedback (superadmin)', () => {
  test('regular user visiting /app/superadmin/owner-feedbacks is redirected to /app', async ({
    page,
  }) => {
    mockLoginAndUser(page, MOCK_REGULAR_USER)
    const loginPage = new LoginPage(page)
    await loginPage.goto()
    await loginPage.login(MOCK_REGULAR_USER.email, 'password123')
    await expect(page).toHaveURL(/\/app(\/)?$/)

    const appPage = new SuperadminAppPage(page)
    await appPage.goto('/app/superadmin/owner-feedbacks')
    await appPage.expectRedirectedToApp()
  })

  test('superadmin can open Owner feedbacks page and see list with submitter, message, status', async ({
    page,
  }) => {
    await loginAsSuperadmin(page)
    const feedbacks = [
      {
        uuid: 'ofb-sa-001',
        title: 'Dark mode',
        message: 'I need dark mode for the public menu.',
        status: 'pending',
        created_at: '2026-02-20T12:00:00.000000Z',
        submitter: {
          uuid: MOCK_REGULAR_USER.uuid,
          name: 'Test Owner',
          email: 'owner@example.com',
        },
        restaurant: null,
      },
    ]
    mockSuperadminOwnerFeedbacksList(page, feedbacks)

    const appPage = new SuperadminAppPage(page)
    await appPage.navigateToOwnerFeedbacks()
    await appPage.expectOwnerFeedbacksPageUrl()

    const feedbacksPage = new SuperadminOwnerFeedbacksPage(page)
    await feedbacksPage.expectPageVisible()
    await feedbacksPage.expectFeedbacksListVisible()
    await feedbacksPage.expectFeedbackRowWithSubmitterVisible('Test Owner')
    await feedbacksPage.expectFeedbackRowWithMessageVisible('I need dark mode for the public menu.')
    await feedbacksPage.expectFeedbackRowShowsStatus('I need dark mode for the public menu.', 'Pending')
  })

  test('superadmin can toggle feedback status to reviewed and see success', async ({ page }) => {
    await loginAsSuperadmin(page)
    const feedbacks = [
      {
        uuid: 'ofb-sa-002',
        title: 'PDF export',
        message: 'Add export to PDF for the menu.',
        status: 'pending',
        created_at: '2026-02-20T12:00:00.000000Z',
        submitter: {
          uuid: MOCK_REGULAR_USER.uuid,
          name: 'Test Owner',
          email: 'owner@example.com',
        },
        restaurant: null,
      },
    ]
    mockSuperadminOwnerFeedbacksList(page, feedbacks)
    mockSuperadminOwnerFeedbackPatch(page, feedbacks)

    const appPage = new SuperadminAppPage(page)
    await appPage.navigateToOwnerFeedbacks()
    const feedbacksPage = new SuperadminOwnerFeedbacksPage(page)
    await feedbacksPage.expectPageVisible()
    await feedbacksPage.expectFeedbackRowWithMessageVisible('Add export to PDF for the menu.')
    await feedbacksPage.expectFeedbackRowShowsStatus('Add export to PDF for the menu.', 'Pending')
    await feedbacksPage.clickMarkReviewedForRowWithMessage('Add export to PDF for the menu.')
    await feedbacksPage.expectFeedbackRowShowsStatus('Add export to PDF for the menu.', 'Reviewed')
    await feedbacksPage.expectToastSuccess('Feedback updated')
  })

  test('superadmin can toggle feedback status from reviewed to pending', async ({ page }) => {
    await loginAsSuperadmin(page)
    const feedbacks = [
      {
        uuid: 'ofb-sa-003',
        title: 'Done feature',
        message: 'This was already implemented.',
        status: 'reviewed',
        created_at: '2026-02-20T11:00:00.000000Z',
        submitter: {
          uuid: MOCK_REGULAR_USER.uuid,
          name: 'Test Owner',
          email: 'owner@example.com',
        },
        restaurant: null,
      },
    ]
    mockSuperadminOwnerFeedbacksList(page, feedbacks)
    mockSuperadminOwnerFeedbackPatch(page, feedbacks)

    const feedbacksPage = new SuperadminOwnerFeedbacksPage(page)
    await feedbacksPage.goTo()
    await feedbacksPage.expectFeedbackRowShowsStatus('This was already implemented.', 'Reviewed')
    await feedbacksPage.clickMarkPendingForRowWithMessage('This was already implemented.')
    await feedbacksPage.expectToastSuccess('Feedback updated')
    await feedbacksPage.expectFeedbackRowShowsStatus('This was already implemented.', 'Pending')
  })
})
