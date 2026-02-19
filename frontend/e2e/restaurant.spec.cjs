// @ts-check
/**
 * E2E tests for the Restaurant module (list, create, manage, menu, settings).
 * API calls are mocked. "Translate by default" / "Translate from default" is not tested (not yet implemented).
 */
const { test, expect } = require('@playwright/test')
const { RestaurantMenuTabPage } = require('./pages/RestaurantMenuTabPage.cjs')
const { CategoryMenuItemsPage } = require('./pages/CategoryMenuItemsPage.cjs')
const { MenuItemFormPage } = require('./pages/MenuItemFormPage.cjs')

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
  let state = { ...restaurant }
  page.route(/^.*\/api\/restaurants\/[^/]+$/, (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...state } }),
      })
    }
    if (route.request().method() === 'PATCH' || route.request().method() === 'PUT') {
      const body = JSON.parse(route.request().postData() || '{}')
      state = { ...state, ...body }
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Restaurant updated.', data: { ...state } }),
      })
    }
    if (route.request().method() === 'DELETE') {
      return route.fulfill({ status: 204, body: '' })
    }
    return route.continue()
  })
}

function mockRestaurantMenus(page, menus = [{ uuid: 'menu-1', name: 'Main menu', is_active: true, sort_order: 0 }]) {
  const menusList = Array.isArray(menus) ? [...menus] : menus
  page.route(/^.*\/api\/restaurants\/[^/]+\/menus\/reorder$/, (route) => {
    if (route.request().method() === 'POST') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ message: 'Order updated.' }) })
    }
    return route.continue()
  })
  page.route(/^.*\/api\/restaurants\/[^/]+\/menus(\/[^/]+)?(\/categories)?(\/reorder)?$/, (route) => {
    const url = route.request().url()
    const method = route.request().method()
    if (method === 'GET' && url.includes('/menus') && !url.includes('/categories')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: menusList }),
      })
    }
    if (method === 'POST' && url.endsWith('/menus') && !url.includes('/reorder')) {
      const body = JSON.parse(route.request().postData() || '{}')
      const name = body.name != null && body.name !== '' ? body.name : null
      const newMenu = {
        uuid: 'menu-new-' + Date.now(),
        name,
        is_active: body.is_active !== false,
        sort_order: typeof body.sort_order === 'number' ? body.sort_order : menusList.length,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      menusList.push(newMenu)
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Menu created.', data: newMenu }),
      })
    }
    if (method === 'PATCH' && url.match(/\/menus\/([^/]+)$/) && !url.includes('/categories')) {
      const menuUuid = url.match(/\/menus\/([^/]+)$/)[1]
      const body = JSON.parse(route.request().postData() || '{}')
      const idx = menusList.findIndex((m) => m.uuid === menuUuid)
      const current = idx >= 0 ? menusList[idx] : menusList[0]
      const updated = {
        ...current,
        ...(body.name !== undefined && { name: body.name }),
        ...(body.is_active !== undefined && { is_active: body.is_active }),
        updated_at: new Date().toISOString(),
      }
      if (idx >= 0) menusList[idx] = updated
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Menu updated.', data: updated }),
      })
    }
    return route.continue()
  })
}

function mockRestaurantCategories(page, categories = []) {
  const state = Array.isArray(categories) ? categories.map((c) => ({ ...c })) : []
  page.route(/^.*\/api\/restaurants\/[^/]+\/menus\/[^/]+\/categories(\/[^/]+)?(\/reorder)?$/, (route) => {
    const url = route.request().url()
    const method = route.request().method()
    if (method === 'GET' && !url.match(/\/categories\/[^/]+$/) && !url.endsWith('/reorder')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [...state] }),
      })
    }
    if (method === 'POST' && !url.endsWith('/reorder')) {
      const body = JSON.parse(route.request().postData() || '{}')
      const name = body.translations?.en?.name ?? 'New category'
      const newCat = {
        uuid: 'cat-' + Date.now(),
        sort_order: state.length,
        is_active: true,
        translations: { en: { name } },
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      state.push(newCat)
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Category created.', data: newCat }),
      })
    }
    if (method === 'PATCH') {
      const match = url.match(/\/categories\/([^/]+)$/)
      const catUuid = match?.[1]
      const body = JSON.parse(route.request().postData() || '{}')
      const idx = state.findIndex((c) => c.uuid === catUuid)
      if (idx >= 0 && body.translations) {
        state[idx] = { ...state[idx], ...body, updated_at: new Date().toISOString() }
        if (body.translations.en) state[idx].translations = { ...state[idx].translations, ...body.translations }
      }
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Category updated.', data: state[idx] || {} }),
      })
    }
    if (method === 'DELETE') {
      const match = url.match(/\/categories\/([^/]+)$/)
      const catUuid = match?.[1]
      const idx = state.findIndex((c) => c.uuid === catUuid)
      if (idx >= 0) state.splice(idx, 1)
      return route.fulfill({ status: 204, body: '' })
    }
    if (method === 'POST' && url.endsWith('/reorder')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ message: 'Order updated.' }) })
    }
    return route.continue()
  })
}

function mockRestaurantMenuItems(page, items = []) {
  const state = Array.isArray(items) ? items.map((i) => ({ ...i })) : []
  page.route(/^.*\/api\/restaurants\/[^/]+\/menu-items(\/[^/]+)?$/, (route) => {
    const url = route.request().url()
    const method = route.request().method()
    if (method === 'GET' && !url.match(/\/menu-items\/[^/]+$/)) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [...state] }),
      })
    }
    if (method === 'GET' && url.match(/\/menu-items\/[^/]+$/)) {
      const match = url.match(/\/menu-items\/([^/]+)$/)
      const itemUuid = match?.[1]
      const item = state.find((i) => i.uuid === itemUuid)
      if (item) {
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: item }) })
      }
      return route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Menu item not found.' }) })
    }
    if (method === 'POST' && !url.match(/\/menu-items\/[^/]+$/)) {
      const body = JSON.parse(route.request().postData() || '{}')
      const name = body.translations?.en?.name ?? 'New Item'
      const newItem = {
        uuid: 'item-' + Date.now(),
        category_uuid: body.category_uuid ?? null,
        sort_order: body.sort_order ?? state.length,
        price: body.price ?? null,
        translations: body.translations ?? { en: { name, description: null } },
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      state.push(newItem)
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Menu item created.', data: newItem }),
      })
    }
    if (method === 'PATCH') {
      const match = url.match(/\/menu-items\/([^/]+)$/)
      const itemUuid = match?.[1]
      const body = JSON.parse(route.request().postData() || '{}')
      const idx = state.findIndex((i) => i.uuid === itemUuid)
      if (idx >= 0) {
        if (body.translations) state[idx].translations = { ...state[idx].translations, ...body.translations }
        if (body.price !== undefined) state[idx].price = body.price
        if (body.category_uuid !== undefined) state[idx].category_uuid = body.category_uuid
        if (body.sort_order !== undefined) state[idx].sort_order = body.sort_order
        state[idx].updated_at = new Date().toISOString()
      }
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Menu item updated.', data: state[idx] || {} }),
      })
    }
    return route.continue()
  })
  page.route(/^.*\/api\/restaurants\/[^/]+\/categories\/[^/]+\/menu-items\/reorder$/, (route) => {
    if (route.request().method() === 'POST') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ message: 'Order updated.' }) })
    }
    return route.continue()
  })
}

function mockUserMenuItems(page, items = []) {
  page.route(/^.*\/api\/menu-items(\/[^/]+)?$/, (route) => {
    const method = route.request().method()
    const url = route.request().url()
    if (method === 'GET' && !url.match(/\/menu-items\/[^/]+$/)) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: items }) })
    }
    if (method === 'GET' && url.match(/\/menu-items\/[^/]+$/)) {
      const uuid = url.split('/menu-items/')[1]?.split('?')[0]
      const item = items.find((i) => i.uuid === uuid)
      if (item) {
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: item }) })
      }
      return route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Menu item not found.' }) })
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
    mockRestaurantMenus(page, [])
    mockRestaurantCategories(page, [])
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
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
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
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await expect(page.getByRole('tab', { name: 'Profile' })).toBeVisible()
    await expect(page.getByLabel('Restaurant name')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Delete restaurant' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeVisible()
  })

  test('restaurant Menu tab shows Menus & categories and Manage menu items link when menu exists; Add menu item is absent', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectMenusHeadingVisible()
    await expect(page.getByRole('link', { name: 'Manage menu items' })).toBeVisible()
    await menuTab.expectAddMenuItemAbsent()
  })

  test('restaurant Menu tab shows Create menu when no menus', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page, [])
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await page.getByRole('tab', { name: 'Menu' }).click()

    await expect(page.getByRole('heading', { name: /Menus & categories/i })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Create menu' })).toBeVisible()
  })

  test('Add menu button is visible when at least one menu exists', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectMenusHeadingVisible()
    await menuTab.expectAddMenuButtonVisible()
  })

  test('Add second menu with name via modal succeeds and new menu appears in selector', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectAddMenuButtonVisible()
    await menuTab.openAddMenuModal()
    await menuTab.expectAddMenuModalOpen()
    await menuTab.setMenuName('Lunch menu')
    await menuTab.submitCreateMenu()
    await menuTab.expectAddMenuModalClosed()
    await menuTab.expectMenuInSelector('Lunch menu')
  })

  test('Add second menu without name via modal succeeds and Unnamed menu appears', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectAddMenuButtonVisible()
    await menuTab.openAddMenuModal()
    await menuTab.expectAddMenuModalOpen()
    await menuTab.submitCreateMenu()
    await menuTab.expectAddMenuModalClosed()
    await menuTab.expectMenuInSelector('Unnamed menu')
  })

  test('Cancel in Add menu modal closes modal without creating menu', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectAddMenuButtonVisible()
    await menuTab.openAddMenuModal()
    await menuTab.expectAddMenuModalOpen()
    await menuTab.cancelAddMenuModal()
    await menuTab.expectAddMenuModalClosed()
    await menuTab.expectMenuInSelector('Main menu')
  })

  test('FAB opens speed-dial with Add menu and Add category; clicking FAB does not open a form directly', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectMenusHeadingVisible()
    await menuTab.openFAB()
    await menuTab.expectFABSpeedDialVisible()
    await menuTab.expectAddMenuModalClosed()
    await menuTab.expectCategoryModalClosed()
  })

  test('FAB Add category opens Add category modal', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openFAB()
    await menuTab.expectFABSpeedDialVisible()
    await menuTab.clickFABAddCategory()
    await menuTab.expectAddCategoryModalOpen()
  })

  test('FAB Add menu opens Add menu modal', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openFAB()
    await menuTab.expectFABSpeedDialVisible()
    await menuTab.clickFABAddMenu()
    await menuTab.expectAddMenuModalOpen()
  })

  test('FAB speed-dial closes on backdrop click without opening a form', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openFAB()
    await menuTab.expectFABSpeedDialVisible()
    await menuTab.closeFABWithBackdrop()
    await menuTab.expectFABSpeedDialClosed()
    await menuTab.expectAddMenuModalClosed()
    await menuTab.expectCategoryModalClosed()
  })

  test('FAB speed-dial closes when clicking FAB again without opening a form', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openFAB()
    await menuTab.expectFABSpeedDialVisible()
    await menuTab.closeFABWithButton()
    await menuTab.expectFABSpeedDialClosed()
    await menuTab.expectAddMenuModalClosed()
    await menuTab.expectCategoryModalClosed()
  })

  test('owner can rename menu via Rename menu modal and see name update in selector', async ({ page }) => {
    const menus = [{ uuid: 'menu-rename-1', name: 'Dinner', is_active: true, sort_order: 0, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }]
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page, menus)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectMenuInSelector('Dinner')
    await menuTab.clickRenameMenu()
    await menuTab.expectRenameMenuModalOpen()
    await menuTab.setRenameMenuName('Evening menu')
    await menuTab.submitRenameMenu()
    await menuTab.expectRenameMenuModalClosed()
    await menuTab.expectMenuInSelector('Evening menu')
  })

  test('restaurant Availability tab shows heading and schedule', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
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
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
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
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}/menu-items/new`)

    await expect(page.getByRole('heading', { name: 'Add menu item' })).toBeVisible()
    await expect(page.getByLabel(/name.*en/i)).toBeVisible()
    await expect(page.getByRole('button', { name: 'Create item' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible()
  })

  test('Menu tab Manage menu items link goes to standalone menu items page', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)
    mockUserMenuItems(page, [])

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await page.getByRole('tab', { name: 'Menu' }).click()
    await page.getByRole('link', { name: 'Manage menu items' }).click()

    await expect(page).toHaveURL(/\/app\/menu-items\/?(\?|$)/)
    await expect(page.getByRole('heading', { name: 'Menu items' })).toBeVisible({ timeout: 5000 })
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

  // --- Page-object-only flows: categories, category items, menu item form ---

  test('owner can add category with name and see it in list', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectMenusHeadingVisible()
    await menuTab.expectCategoriesHeadingVisible()
    await menuTab.expectNoCategoriesMessage()
    await menuTab.openAddCategoryModal()
    await menuTab.expectAddCategoryModalOpen()
    await menuTab.setCategoryName('Starters')
    await menuTab.submitSaveCategory()
    await menuTab.expectCategoryModalClosed()
    await menuTab.expectCategoryVisible('Starters')
  })

  test('owner can edit category name', async ({ page }) => {
    const startCat = { uuid: 'cat-1', sort_order: 0, is_active: true, translations: { en: { name: 'Mains' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [startCat])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectCategoryVisible('Mains')
    await menuTab.openEditCategoryModal('Mains')
    await menuTab.expectEditCategoryModalOpen()
    await menuTab.setCategoryName('Main courses')
    await menuTab.submitSaveCategory()
    await menuTab.expectCategoryModalClosed()
    await menuTab.expectCategoryVisible('Main courses')
  })

  test('owner can remove category after confirming', async ({ page }) => {
    const startCat = { uuid: 'cat-remove', sort_order: 0, is_active: true, translations: { en: { name: 'To Remove' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [startCat])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectCategoryVisible('To Remove')
    await menuTab.openDeleteCategoryModal('To Remove')
    await menuTab.expectRemoveCategoryModalOpen()
    await menuTab.confirmRemoveCategory()
    await menuTab.expectNoCategoriesMessage()
  })

  test('owner can open Manage items for category and see category items page', async ({ page }) => {
    const cat = { uuid: 'cat-items', sort_order: 0, is_active: true, translations: { en: { name: 'Drinks' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.clickManageItemsForCategory('Drinks')

    await expect(page).toHaveURL(new RegExp(`/app/restaurants/${MOCK_RESTAURANT.uuid}/categories/${cat.uuid}/items`))
    const categoryItemsPage = new CategoryMenuItemsPage(page)
    await categoryItemsPage.expectCategoryHeading('Drinks')
    await categoryItemsPage.expectAddMenuItemButtonVisible()
    await categoryItemsPage.expectEmptyState()
  })

  test('owner can open category items page, click Add menu item, and see modal with search and toggle', async ({ page }) => {
    const cat = { uuid: 'cat-add-item', sort_order: 0, is_active: true, translations: { en: { name: 'Starters' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const categoryItemsPage = new CategoryMenuItemsPage(page)
    await categoryItemsPage.goTo(MOCK_RESTAURANT.uuid, cat.uuid, { name: 'Starters' })
    await categoryItemsPage.expectCategoryHeading('Starters')
    await categoryItemsPage.expectAddMenuItemButtonVisible()
    await categoryItemsPage.clickAddMenuItemButton()
    await categoryItemsPage.expectAddItemModalOpen()
    await categoryItemsPage.expectAddItemModalSearchVisible()
    await categoryItemsPage.expectAddItemModalToggleVisible()
    await categoryItemsPage.closeAddItemModal()
    await categoryItemsPage.expectAddItemModalClosed()
  })

  test('owner can add menu item to category via modal and see list update', async ({ page }) => {
    const cat = { uuid: 'cat-add-flow', sort_order: 0, is_active: true, translations: { en: { name: 'Mains' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    const itemNotInCategory = { uuid: 'item-standalone', category_uuid: null, sort_order: 0, price: '10.00', translations: { en: { name: 'Burger', description: null } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [itemNotInCategory])
    mockRestaurantLanguagesAndTranslations(page)

    const categoryItemsPage = new CategoryMenuItemsPage(page)
    await categoryItemsPage.goTo(MOCK_RESTAURANT.uuid, cat.uuid, { name: 'Mains' })
    await categoryItemsPage.expectEmptyState()
    await categoryItemsPage.clickAddMenuItemButton()
    await categoryItemsPage.expectAddItemModalOpen()
    await categoryItemsPage.addItemToCategoryInModal('Burger')
    await categoryItemsPage.closeAddItemModal()
    await categoryItemsPage.expectAddItemModalClosed()
    await categoryItemsPage.expectItemVisible('Burger')
    await categoryItemsPage.expectItemCount(1)
  })

  test('owner can edit restaurant profile name and save', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}`)
    await page.getByRole('tab', { name: 'Profile' }).click()
    await page.getByLabel('Restaurant name').fill('Updated Pizza')
    await page.getByRole('button', { name: 'Save changes' }).click()
    await expect(page.getByLabel('Restaurant name')).toHaveValue('Updated Pizza', { timeout: 10000 })
  })

  test('owner can toggle menu active from Menu tab', async ({ page }) => {
    const menus = [{ uuid: 'menu-toggle', name: 'Dinner', is_active: true, sort_order: 0, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }]
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page, menus)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.expectMenuInSelector('Dinner')
    await menuTab.toggleMenuActive('Dinner')
    await menuTab.expectMenuInSelector('Dinner')
  })

  test('owner can edit menu item and set price', async ({ page }) => {
    const cat = { uuid: 'cat-edit', sort_order: 0, is_active: true, translations: { en: { name: 'Mains' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    const existingItem = { uuid: 'item-edit-1', category_uuid: cat.uuid, sort_order: 0, price: null, translations: { en: { name: 'Burger', description: null } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [existingItem])
    mockRestaurantLanguagesAndTranslations(page)

    const formPage = new MenuItemFormPage(page)
    await formPage.goToEdit(MOCK_RESTAURANT.uuid, existingItem.uuid)
    await formPage.expectEditMenuItemHeading()
    await formPage.setPrice('12.50')
    await formPage.submitSaveChanges()

    await expect(page).toHaveURL(new RegExp(`/app/restaurants/${MOCK_RESTAURANT.uuid}`))
  })
})
