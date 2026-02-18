// @ts-check
/**
 * E2E tests for the Restaurant module (list, create, manage, menu, settings).
 * API calls are mocked. "Translate by default" / "Translate from default" is not tested (not yet implemented).
 */
const { test, expect } = require('@playwright/test')

const MOCK_VERIFIED_USER = {
  id: 1,
  name: 'Test Owner',
  email: 'verified@example.com',
  email_verified_at: '2025-01-01T00:00:00.000000Z',
}

const MOCK_TOKEN = 'mock-sanctum-token'

const MOCK_RESTAURANT = {
  uuid: 'rstr-1111-2222-3333',
  name: 'Test Pizza',
  slug: 'test-pizza',
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
}

// Match list (GET /api/restaurants or with query) and create (POST /api/restaurants), not e.g. GET /api/restaurants/uuid
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
    if (route.request().method() === 'POST') {
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Restaurant created.',
          data: { ...MOCK_RESTAURANT, uuid: 'rstr-new-1', name: 'New Restaurant', slug: 'new-restaurant' },
        }),
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
        body: JSON.stringify({ data: restaurant }),
      })
    }
    if (route.request().method() === 'PATCH') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Restaurant updated.', data: { ...restaurant, ...JSON.parse(route.request().postData() || '{}') } }),
      })
    }
    if (route.request().method() === 'DELETE') {
      return route.fulfill({ status: 204, body: '' })
    }
    return route.continue()
  })
}

function mockRestaurantMenuItems(page, items = []) {
  page.route(/^.*\/api\/restaurants\/[^/]+\/menu-items(\/[^/]+)?$/, (route) => {
    const url = route.request().url()
    const method = route.request().method()
    if (method === 'GET' && !url.match(/\/menu-items\/[^/]+$/)) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: items }),
      })
    }
    if (method === 'POST' && !url.match(/\/menu-items\/[^/]+$/)) {
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Menu item created.',
          data: { uuid: 'item-' + Date.now(), sort_order: 0, translations: { en: { name: 'New Item', description: null } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
        }),
      })
    }
    return route.continue()
  })
}

function mockRestaurantLanguagesAndTranslations(page) {
  page.route(/^.*\/api\/restaurants\/[^/]+\/languages.*$/, (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: ['en'] }),
      })
    }
    return route.continue()
  })
  page.route(/^.*\/api\/restaurants\/[^/]+\/translations.*$/, (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { en: { description: null } } }),
      })
    }
    return route.continue()
  })
}

test.describe('Restaurant module', () => {
  test('unauthenticated visit to /app/restaurants redirects to login', async ({ page }) => {
    await page.goto('/app/restaurants')
    await expect(page).toHaveURL(/\/login/)
    await expect(page).toHaveURL(/\?redirect=/)
  })

  test('can navigate to restaurants from app sidebar', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [])
    await page.getByRole('link', { name: /restaurants/i }).click()
    await expect(page).toHaveURL(/\/app\/restaurants/)
    await expect(page.getByRole('heading', { name: /your restaurants/i })).toBeVisible()
  })

  test('restaurant list shows empty state when no restaurants', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [])
    await page.getByRole('link', { name: /restaurants/i }).click()
    await expect(page).toHaveURL(/\/app\/restaurants/)
    await expect(page.getByRole('heading', { name: /your restaurants/i })).toBeVisible()
    await expect(page.getByRole('heading', { name: /add your first location/i })).toBeVisible({ timeout: 8000 })
    await expect(page.getByRole('link', { name: 'Create restaurant' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Add restaurant' })).toBeVisible()
  })

  test('restaurant list shows Add restaurant button in header', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [])
    await page.goto('/app/restaurants')

    await expect(page.getByRole('link', { name: 'Add restaurant' })).toBeVisible()
  })

  test('create restaurant form shows fields and Create restaurant submit', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [])
    await page.goto('/app/restaurants/new')

    await expect(page.getByRole('heading', { name: 'Add new restaurant' })).toBeVisible()
    await expect(page.getByLabel('Restaurant name')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Create restaurant' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Basic information' })).toBeVisible()
  })

  test('create restaurant with name succeeds and navigates', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [])
    mockRestaurantGet(page, { ...MOCK_RESTAURANT, uuid: 'rstr-new-1', name: 'New Restaurant', slug: 'new-restaurant' })
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto('/app/restaurants/new')
    await page.getByLabel('Restaurant name').fill('New Restaurant')
    await page.getByRole('button', { name: 'Create restaurant' }).click()

    await expect(page).toHaveURL(/\/app\/restaurants\/rstr-new-1/)
    await expect(page.getByRole('heading', { name: 'New Restaurant' }).first()).toBeVisible({ timeout: 8000 })
  })

  test('restaurant manage page shows Profile, Menu, Availability, Settings tabs', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)

    await expect(page).toHaveURL(new RegExp(`/app/restaurants/${MOCK_RESTAURANT.uuid}`))
    await expect(page.getByRole('tab', { name: 'Profile' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Menu' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Availability' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Settings' })).toBeVisible()
  })

  test('restaurant Profile tab has form and Delete restaurant button', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await expect(page.getByRole('tab', { name: 'Profile' })).toBeVisible()
    await expect(page.getByLabel('Restaurant name')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Delete restaurant' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeVisible()
  })

  test('restaurant Menu tab shows Menu management and Categories / Items', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await page.getByRole('tab', { name: 'Menu' }).click()

    await expect(page.getByRole('heading', { name: 'Menu management' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Categories' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Items' })).toBeVisible()
  })

  test('restaurant Availability tab shows heading and schedule', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await page.getByRole('tab', { name: 'Availability' }).click()

    await expect(page.getByRole('heading', { name: 'Availability' })).toBeVisible()
  })

  test('restaurant Settings tab shows Currency and Languages', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await page.getByRole('tab', { name: 'Settings' }).click()

    await expect(page.getByRole('heading', { name: 'Settings' })).toBeVisible()
    await expect(page.getByRole('combobox', { name: /currency/i })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Languages' })).toBeVisible()
  })

  test('add menu item page shows name field and Create item', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}/menu-items/new`)

    await expect(page.getByRole('heading', { name: 'Add menu item' })).toBeVisible()
    await expect(page.getByLabel(/name.*en/i)).toBeVisible()
    await expect(page.getByRole('button', { name: 'Create item' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible()
  })

  test('Menu tab FAB opens Add menu item modal', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await page.getByRole('tab', { name: 'Menu' }).click()
    await page.getByRole('button', { name: 'Add menu item' }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog.getByRole('heading', { name: 'Add menu item' })).toBeVisible()
    await expect(dialog.getByPlaceholder('e.g. Margherita Pizza')).toBeVisible()
    await expect(dialog.getByRole('button', { name: 'Save' })).toBeVisible()
  })

  test('restaurant not found shows message and back link', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [])
    page.route(/^.*\/api\/restaurants\/[^/]+$/, (route) => {
      if (route.request().method() === 'GET') {
        return route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Not found.' }) })
      }
      return route.continue()
    })

    await page.goto('/app/restaurants/rstr-nonexistent-uuid')

    await expect(page.getByText('Restaurant not found.')).toBeVisible({ timeout: 5000 })
    await expect(page.getByRole('link', { name: /back|restaurants/i }).first()).toBeVisible()
  })
})
