// @ts-check
/**
 * E2E tests for the Feedbacks module: owner flow (list, approve, reject, delete) and public flow (reviews section, submit feedback).
 * API calls are mocked. No selectors in test bodies; all interactions via Page Objects.
 */
const { test, expect } = require('@playwright/test')
const { FeedbacksLandingPage } = require('./pages/FeedbacksLandingPage.cjs')
const { FeedbacksListPage } = require('./pages/FeedbacksListPage.cjs')
const { PublicRestaurantPage } = require('./pages/PublicRestaurantPage.cjs')

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

const MOCK_RESTAURANT = {
  uuid: 'rstr-fb-1111',
  name: 'Pizza Place',
  slug: 'pizza-place',
  address: '123 Main St',
  phone: '',
  email: '',
  website: '',
  tagline: '',
  description: '',
  default_locale: 'en',
  languages: ['en'],
  currency: 'USD',
  logo_url: null,
  banner_url: null,
  social_links: { facebook: '', instagram: '', twitter: '', linkedin: '' },
  operating_hours: {},
  created_at: '2025-01-01T00:00:00.000000Z',
  updated_at: '2025-01-01T00:00:00.000000Z',
}

function mockLoginAndUser(page) {
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
        user: MOCK_VERIFIED_USER,
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
      body: JSON.stringify({ user: MOCK_VERIFIED_USER }),
    })
  })
}

async function loginAsVerifiedUser(page) {
  mockLoginAndUser(page)
  await page.goto('/login')
  await page.getByPlaceholder(/you@example\.com/i).fill('verified@example.com')
  await page.getByPlaceholder(/••••••••/).fill('password123')
  await page.getByRole('button', { name: /sign in/i }).click()
  await expect(page).toHaveURL(/\/app/)
  await expectNoLegacyAuthInStorage(page)

  // After any full navigation/reload, the app restores auth via POST /api/auth/refresh (cookie-based).
  // Mock it so tests that use page.goto() stay authenticated.
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
        user: MOCK_VERIFIED_USER,
        token: MOCK_TOKEN,
        token_type: 'Bearer',
      }),
    })
  })
}

const LIST_OR_CREATE_RESTAURANTS = /\/api\/restaurants(\?.*)?$/

function mockRestaurantList(page, restaurants = [], meta = null) {
  const m = meta ?? { current_page: 1, last_page: 1, per_page: 15, total: restaurants.length }
  page.route(LIST_OR_CREATE_RESTAURANTS, (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: restaurants, meta: m }),
      })
    }
    return route.continue()
  })
}

function mockRestaurantGet(page, restaurant = MOCK_RESTAURANT) {
  page.route(/^.*\/api\/restaurants\/[^/]+$/, (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...restaurant } }),
      })
    }
    return route.continue()
  })
}

/** Mock GET /api/restaurants/:id/feedbacks and PATCH/DELETE /api/restaurants/:id/feedbacks/:fid */
function mockRestaurantFeedbacks(page, restaurantUuid, initialFeedbacks = []) {
  const state = initialFeedbacks.map((f) => ({ ...f }))

  page.route(new RegExp(`/api/restaurants/${restaurantUuid}/feedbacks$`), (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [...state] }),
      })
    }
    return route.continue()
  })

  page.route(
    new RegExp(`/api/restaurants/${restaurantUuid}/feedbacks/([^/]+)$`),
    (route) => {
      const method = route.request().method()
      const url = route.request().url()
      const fidMatch = url.match(/\/feedbacks\/([^/]+)$/)
      const fid = fidMatch?.[1]
      if (method === 'PATCH' || method === 'PUT') {
        const body = JSON.parse(route.request().postData() || '{}')
        const idx = state.findIndex((f) => f.uuid === fid)
        if (idx !== -1) {
          state[idx] = { ...state[idx], is_approved: !!body.is_approved, updated_at: new Date().toISOString() }
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
              message: 'Feedback updated.',
              data: state[idx],
            }),
          })
        }
      }
      if (method === 'DELETE') {
        const idx = state.findIndex((f) => f.uuid === fid)
        if (idx !== -1) {
          state.splice(idx, 1)
          return route.fulfill({ status: 204, body: '' })
        }
      }
      return route.continue()
    }
  )
}

/** Mock GET /api/public/restaurants/:slug and POST /api/public/restaurants/:slug/feedback */
function mockPublicRestaurantWithFeedbacks(page, slug, data = {}) {
  const payload = {
    name: 'Pizza Place',
    slug: slug || 'pizza-place',
    description: null,
    currency: 'USD',
    languages: ['en'],
    menu_items: [],
    operating_hours: {},
    feedbacks: [],
    ...data,
  }

  page.route(/^.*\/api\/public\/restaurants\/[^/]+$/, (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    const urlSlug = route.request().url().match(/\/public\/restaurants\/([^/?#]+)/)?.[1]
    if (decodeURIComponent(urlSlug || '') !== slug) return route.continue()
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { ...payload } }),
    })
  })

  page.route(new RegExp(`/api/public/restaurants/${slug}/feedback`), (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    const body = JSON.parse(route.request().postData() || '{}')
    const created = {
      uuid: 'fb-' + Date.now(),
      rating: body.rating ?? 5,
      text: body.text ?? '',
      name: body.name ?? '',
      is_approved: false,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    return route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        message: 'Thank you for your feedback.',
        data: created,
      }),
    })
  })
}

// --- Owner flow ---

test.describe('Feedbacks (owner)', () => {
  test('unauthenticated visit to /app/feedbacks redirects to login', async ({ page }) => {
    await page.goto('/app/feedbacks')
    await expect(page).toHaveURL(/\/login/)
    await expect(page).toHaveURL(/\?redirect=/)
  })

  test('owner can open Feedbacks from sidenav and see landing', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [])
    const landingPage = new FeedbacksLandingPage(page)
    await landingPage.goToViaSidenav()
    await landingPage.expectEmptyStateNoRestaurants()
  })

  test('owner with one restaurant is redirected to feedbacks list', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantFeedbacks(page, MOCK_RESTAURANT.uuid, [])
    await page.goto('/app/feedbacks')
    await expect(page).toHaveURL(new RegExp(`/app/feedbacks/restaurants/${MOCK_RESTAURANT.uuid}`))
    const listPage = new FeedbacksListPage(page)
    await listPage.expectFeedbacksListHeadingVisible()
    await listPage.expectEmptyState()
  })

  test('owner with multiple restaurants sees list and can open a restaurant feedbacks', async ({ page }) => {
    const r2 = { ...MOCK_RESTAURANT, uuid: 'rstr-fb-2222', name: 'Burger Joint', slug: 'burger-joint' }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT, r2])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantFeedbacks(page, MOCK_RESTAURANT.uuid, [])
    mockRestaurantGet(page, r2)
    mockRestaurantFeedbacks(page, r2.uuid, [])

    const landingPage = new FeedbacksLandingPage(page)
    await landingPage.goTo()
    await landingPage.expectRestaurantLinkVisible('Pizza Place')
    await landingPage.expectRestaurantLinkVisible('Burger Joint')
    await landingPage.goToRestaurantFeedbacks('Pizza Place')
    await expect(page).toHaveURL(new RegExp(`/app/feedbacks/restaurants/${MOCK_RESTAURANT.uuid}`))
    const listPage = new FeedbacksListPage(page)
    await listPage.expectFeedbacksListHeadingVisible()
    await listPage.expectEmptyState()
  })

  test('owner can see feedback list, approve and reject a feedback', async ({ page }) => {
    const pendingFeedback = {
      uuid: 'fb-pending-1',
      rating: 5,
      text: 'Great food!',
      name: 'Jane',
      is_approved: false,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantFeedbacks(page, MOCK_RESTAURANT.uuid, [pendingFeedback])

    const listPage = new FeedbacksListPage(page)
    await listPage.goTo(MOCK_RESTAURANT.uuid)
    await listPage.expectFeedbackVisible('Great food!')
    await listPage.expectFeedbackVisible('Jane')
    await listPage.expectFeedbackCount(1)
    await listPage.clickApproveForFeedbackWithContent('Great food!')
    await listPage.expectToastSuccess('Feedback approved')
  })

  test('owner can reject an approved feedback', async ({ page }) => {
    const approvedFeedback = {
      uuid: 'fb-approved-1',
      rating: 4,
      text: 'Nice place',
      name: 'Bob',
      is_approved: true,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantFeedbacks(page, MOCK_RESTAURANT.uuid, [approvedFeedback])

    const listPage = new FeedbacksListPage(page)
    await listPage.goTo(MOCK_RESTAURANT.uuid)
    await listPage.expectFeedbackVisible('Nice place')
    await listPage.clickRejectForFeedbackWithContent('Nice place')
    await listPage.expectToastSuccess('Feedback rejected')
  })

  test('owner can delete a feedback after confirming', async ({ page }) => {
    const toDelete = {
      uuid: 'fb-delete-1',
      rating: 3,
      text: 'Delete me',
      name: 'Anonymous',
      is_approved: false,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantFeedbacks(page, MOCK_RESTAURANT.uuid, [toDelete])

    await page.on('dialog', (dialog) => dialog.accept())
    const listPage = new FeedbacksListPage(page)
    await listPage.goTo(MOCK_RESTAURANT.uuid)
    await listPage.expectFeedbackVisible('Delete me')
    await listPage.expectFeedbackCount(1)
    await listPage.clickDeleteForFeedbackWithContent('Delete me')
    await listPage.expectToastSuccess('Feedback deleted')
    await listPage.expectEmptyState()
  })
})

// --- Public flow ---

test.describe('Feedbacks (public)', () => {
  test('guest sees Reviews section and feedback form on public restaurant page', async ({ page }) => {
    mockPublicRestaurantWithFeedbacks(page, 'pizza-place', { feedbacks: [] })
    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('pizza-place')
    await publicPage.expectReviewsSectionVisible()
    await publicPage.expectNoReviewsYet()
    await publicPage.expectFeedbackFormVisible()
    await publicPage.expectFeedbackFormInReviewsSection()
  })

  test('template-1: Reviews & feedback section contains reviews and feedback form', async ({ page }) => {
    mockPublicRestaurantWithFeedbacks(page, 'pizza-place', { feedbacks: [] })
    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('pizza-place')
    await publicPage.expectTemplate1Applied()
    await publicPage.expectFeedbackFormInReviewsSection()
    await publicPage.expectNoReviewsYet()
  })

  test('template-2: Reviews & feedback section contains reviews and feedback form', async ({ page }) => {
    mockPublicRestaurantWithFeedbacks(page, 'minimal-cafe', { template: 'template-2', feedbacks: [] })
    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('minimal-cafe')
    await publicPage.expectTemplate2Applied()
    await publicPage.expectFeedbackFormInReviewsSection()
    await publicPage.expectNoReviewsYet()
  })

  test('guest sees approved reviews when present', async ({ page }) => {
    const approved = [
      {
        uuid: 'fb-1',
        rating: 5,
        text: 'Amazing pizza!',
        name: 'Alex',
        created_at: new Date().toISOString(),
      },
    ]
    mockPublicRestaurantWithFeedbacks(page, 'pizza-place', { feedbacks: approved })
    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('pizza-place')
    await publicPage.expectReviewsSectionVisible()
    await publicPage.expectReviewVisible('Amazing pizza!')
    await publicPage.expectReviewVisible('Alex')
    await publicPage.expectFeedbackFormVisible()
    await publicPage.expectFeedbackFormInReviewsSection()
  })

  test('guest can submit valid feedback and sees success message', async ({ page }) => {
    mockPublicRestaurantWithFeedbacks(page, 'pizza-place', { feedbacks: [] })
    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('pizza-place')
    await publicPage.expectFeedbackFormVisible()
    await publicPage.setFeedbackRating(5)
    await publicPage.setFeedbackName('E2E Tester')
    await publicPage.setFeedbackMessage('Loved the menu.')
    await publicPage.submitFeedbackForm()
    await publicPage.expectFeedbackSuccessMessage('Thank you for your feedback')
  })

  test('template-2: guest can submit feedback and sees success message', async ({ page }) => {
    mockPublicRestaurantWithFeedbacks(page, 'minimal-cafe', { template: 'template-2', feedbacks: [] })
    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('minimal-cafe')
    await publicPage.expectTemplate2Applied()
    await publicPage.expectFeedbackFormVisible()
    await publicPage.setFeedbackRating(4)
    await publicPage.setFeedbackName('T2 Guest')
    await publicPage.setFeedbackMessage('Great experience.')
    await publicPage.submitFeedbackForm()
    await publicPage.expectFeedbackSuccessMessage('Thank you for your feedback')
  })

  test('guest submitting feedback without rating sees validation error', async ({ page }) => {
    mockPublicRestaurantWithFeedbacks(page, 'pizza-place', { feedbacks: [] })
    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('pizza-place')
    await publicPage.expectFeedbackFormVisible()
    await publicPage.setFeedbackName('E2E Tester')
    await publicPage.setFeedbackMessage('Some message.')
    await publicPage.submitFeedbackForm()
    await publicPage.expectFeedbackFieldError('Please choose a rating')
  })

  test('guest submitting feedback without name sees validation error', async ({ page }) => {
    mockPublicRestaurantWithFeedbacks(page, 'pizza-place', { feedbacks: [] })
    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('pizza-place')
    await publicPage.setFeedbackRating(4)
    await publicPage.setFeedbackMessage('Some message.')
    await publicPage.submitFeedbackForm()
    await publicPage.expectFeedbackFieldError('Your name is required')
  })
})
