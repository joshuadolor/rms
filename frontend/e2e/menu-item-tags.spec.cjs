// @ts-check
/**
 * E2E tests for the Menu item tags module (read-only default tags).
 * (a) Owner opens Menu item tags from side nav; sees list; breadcrumb.
 * (b) Public menu shows tag on item (optional).
 * API calls are mocked.
 */
const { test, expect } = require('@playwright/test')
const { MenuItemTagsPage } = require('./pages/MenuItemTagsPage.cjs')
const { PublicRestaurantPage } = require('./pages/PublicRestaurantPage.cjs')

const MOCK_VERIFIED_USER_FREE = {
  uuid: 'user-uuid-1',
  name: 'Test Owner',
  email: 'verified@example.com',
  email_verified_at: '2025-01-01T00:00:00.000000Z',
  is_paid: false,
}

const MOCK_TOKEN = 'mock-sanctum-token'

const DEFAULT_TAGS = [
  { uuid: 'tag-default-1', color: '#dc2626', icon: 'local_fire_department', text: 'Spicy', is_default: true },
  { uuid: 'tag-default-2', color: '#16a34a', icon: 'eco', text: 'Vegan', is_default: true },
]

/** Mock login and GET /api/user. */
function mockLoginAndUser(page, userPayload = MOCK_VERIFIED_USER_FREE) {
  const user = { ...MOCK_VERIFIED_USER_FREE, ...userPayload }
  page.route('**/api/login', (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        message: 'Logged in successfully.',
        user,
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
      body: JSON.stringify({ user }),
    })
  })
}

async function loginAsVerifiedUser(page, userPayload = MOCK_VERIFIED_USER_FREE) {
  mockLoginAndUser(page, userPayload)
  await page.goto('/login')
  await page.getByPlaceholder(/you@example\.com/i).fill('verified@example.com')
  await page.getByPlaceholder(/••••••••/).fill('password123')
  await page.getByRole('button', { name: /sign in/i }).click()
  await expect(page).toHaveURL(/\/app/)
}

/** Mock GET /api/menu-item-tags to return the given tags. */
function mockMenuItemTagsList(page, tags = DEFAULT_TAGS) {
  page.route('**/api/menu-item-tags**', (route) => {
    const url = route.request().url()
    const method = route.request().method()
    if (method === 'GET' && !url.match(/\/api\/menu-item-tags\/[^/]+$/)) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [...tags] }),
      })
    }
    return route.continue()
  })
}

/** Mock GET /api/public/restaurants/:slug with menu_items that include tags. */
function mockPublicRestaurantWithTaggedItem(page, slug, menuItems = []) {
  page.route(/^.*\/api\/public\/restaurants\/[^/]+$/, (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    const urlSlug = route.request().url().match(/\/public\/restaurants\/([^/?#]+)/)?.[1]
    if (decodeURIComponent(urlSlug || '') !== slug) return route.continue()
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          name: 'Test Pizza',
          slug: slug || 'test-pizza',
          tagline: null,
          primary_color: null,
          logo_url: null,
          banner_url: null,
          default_locale: 'en',
          template: 'template-1',
          description: null,
          currency: 'USD',
          languages: ['en'],
          locale: 'en',
          menu_items: menuItems,
          operating_hours: {},
          feedbacks: [],
        },
      }),
    })
  })
}

test.describe('Menu item tags', () => {
  test('owner can view menu item tags page and sees list of tags, breadcrumb shows Menu items then Menu item tags', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockMenuItemTagsList(page, DEFAULT_TAGS)

    const tagsPage = new MenuItemTagsPage(page)
    await tagsPage.goTo()

    await expect(page).toHaveURL(/\/app\/menu-item-tags/)
    await tagsPage.expectHeadingVisible()
    await tagsPage.expectBreadcrumbMenuItemsThenTags()
    await tagsPage.expectTagsListVisible()
    await tagsPage.expectTagWithText('Spicy')
    await tagsPage.expectTagWithText('Vegan')
  })

  test('public menu shows menu item with name, price, and tag pills (icon, title, label)', async ({ page }) => {
    const menuItems = [
      {
        uuid: 'item-1',
        name: 'Margherita',
        description: null,
        price: 10,
        is_available: true,
        tags: [{ uuid: 't1', color: '#dc2626', icon: 'local_fire_department', text: 'Spicy' }],
      },
    ]
    mockPublicRestaurantWithTaggedItem(page, 'test-pizza', menuItems)

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('test-pizza')
    await publicPage.expectMenuSectionVisible()
    await publicPage.expectMenuHasAtLeastOneItem()
    await publicPage.expectMenuItemNameVisible('Margherita')
    await publicPage.expectPriceVisibleInMenu('$10.00')
    await publicPage.expectTagIconWithTitleVisible('Spicy')
    await publicPage.expectTagPillVisibleInMenu('Spicy')
  })
})
