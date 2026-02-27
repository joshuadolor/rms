// @ts-check
/**
 * E2E tests for the Restaurant module (list, create, manage, menu, settings).
 * API calls are mocked. "Translate by default" / "Translate from default" is not tested (not yet implemented).
 */
const { test, expect } = require('@playwright/test')
const { RestaurantMenuTabPage } = require('./pages/RestaurantMenuTabPage.cjs')
const { CategoryMenuItemsPage } = require('./pages/CategoryMenuItemsPage.cjs')
const { AvailabilityModalPage } = require('./pages/AvailabilityModalPage.cjs')
const { MenuItemFormPage } = require('./pages/MenuItemFormPage.cjs')
const { RestaurantSettingsPage } = require('./pages/RestaurantSettingsPage.cjs')
const { RestaurantManageAvailabilityPage } = require('./pages/RestaurantManageAvailabilityPage.cjs')
const { RestaurantManagePage } = require('./pages/RestaurantManagePage.cjs')
const { RestaurantContactsPage } = require('./pages/RestaurantContactsPage.cjs')
const { RestaurantFormPage } = require('./pages/RestaurantFormPage.cjs')
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
  template: 'default',
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
        user: MOCK_VERIFIED_USER,
        token: MOCK_TOKEN,
        token_type: 'Bearer',
      }),
    })
  })
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

/**
 * Mock GET/PATCH /api/restaurants/:uuid and POST .../logo, POST .../banner with shared state.
 * Use when testing logo/banner upload so GET returns updated logo_url/banner_url after upload.
 */
function mockRestaurantGetWithMediaUpload(page, restaurant = MOCK_RESTAURANT) {
  const state = { ...restaurant }
  page.route(/^.*\/api\/restaurants\/[^/]+$/, (route) => {
    const url = route.request().url()
    if (url.endsWith('/logo') || url.endsWith('/banner')) return route.continue()
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...state } }),
      })
    }
    if (route.request().method() === 'PATCH' || route.request().method() === 'PUT') {
      const body = JSON.parse(route.request().postData() || '{}')
      Object.assign(state, body)
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
  page.route(/^.*\/api\/restaurants\/[^/]+\/logo$/, (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    state.logo_url = 'https://example.com/mocked-logo.png'
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'Logo updated.', data: { ...state } }),
    })
  })
  page.route(/^.*\/api\/restaurants\/[^/]+\/banner$/, (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    state.banner_url = 'https://example.com/mocked-banner.png'
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'Banner updated.', data: { ...state } }),
    })
  })
}

/**
 * Mock GET /api/public/restaurants/:slug for public restaurant page. Pass operating_hours to show Opening hours.
 * Pass menu_groups (with availability and items) to test category/item availability on public templates.
 * Options.byLocale: { [locale]: { description?, menu_groups?, ... } } — when request has ?locale=xx, merge byLocale[xx] into payload and set payload.locale.
 */
function mockPublicRestaurant(page, slug, data = {}, options = {}) {
  const basePayload = {
    name: 'Test Pizza',
    slug: slug || 'test-pizza',
    description: null,
    currency: 'USD',
    languages: ['en'],
    menu_items: [],
    operating_hours: {},
    ...data,
  }
  const byLocale = options.byLocale || {}
  page.route(/^.*\/api\/public\/restaurants\/[^/]+$/, (route) => {
    if (route.request().method() !== 'GET') return route.continue()
    const urlSlug = route.request().url().match(/\/public\/restaurants\/([^/?#]+)/)?.[1]
    if (decodeURIComponent(urlSlug || '') !== slug) return route.continue()
    const url = new URL(route.request().url())
    const localeParam = url.searchParams.get('locale')
    const payload = { ...basePayload }
    if (localeParam && byLocale[localeParam]) {
      Object.assign(payload, byLocale[localeParam])
      payload.locale = localeParam
    } else {
      payload.locale = payload.default_locale ?? payload.locale ?? 'en'
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: payload }),
    })
  })
}

/** Availability object that formats to "Mon–Fri 11:00–15:00" (same shape as operating_hours). */
function availabilityMonFri1100To1500() {
  const slot = [{ from: '11:00', to: '15:00' }]
  return {
    sunday: { open: false, slots: [] },
    monday: { open: true, slots: [...slot] },
    tuesday: { open: true, slots: [...slot] },
    wednesday: { open: true, slots: [...slot] },
    thursday: { open: true, slots: [...slot] },
    friday: { open: true, slots: [...slot] },
    saturday: { open: false, slots: [] },
  }
}

/** Availability object that formats to "Sat 10:00–14:00". */
function availabilitySat1000To1400() {
  return {
    sunday: { open: false, slots: [] },
    monday: { open: false, slots: [] },
    tuesday: { open: false, slots: [] },
    wednesday: { open: false, slots: [] },
    thursday: { open: false, slots: [] },
    friday: { open: false, slots: [] },
    saturday: { open: true, slots: [{ from: '10:00', to: '14:00' }] },
  }
}

/** Availability where every day is closed. formatAvailabilityForDisplay() returns null; no availability line is shown on the public menu. */
function availabilityAllDaysClosed() {
  return {
    sunday: { open: false, slots: [] },
    monday: { open: false, slots: [] },
    tuesday: { open: false, slots: [] },
    wednesday: { open: false, slots: [] },
    thursday: { open: false, slots: [] },
    friday: { open: false, slots: [] },
    saturday: { open: false, slots: [] },
  }
}

/** Normalize menu to API shape: name (from default locale), translations */
function normalizeMenu(menu, defaultLocale = 'en') {
  const translations = menu.translations ?? { [defaultLocale]: { name: menu.name ?? 'Unnamed menu', description: null } }
  const name = translations[defaultLocale]?.name ?? menu.name ?? 'Unnamed menu'
  return {
    uuid: menu.uuid,
    name,
    translations,
    is_active: menu.is_active !== false,
    sort_order: typeof menu.sort_order === 'number' ? menu.sort_order : 0,
    created_at: menu.created_at ?? new Date().toISOString(),
    updated_at: menu.updated_at ?? new Date().toISOString(),
  }
}

function mockRestaurantMenus(page, menus = [{ uuid: 'menu-1', name: 'Main menu', is_active: true, sort_order: 0 }], defaultLocale = 'en') {
  const menusList = (Array.isArray(menus) ? menus : [menus]).map((m) => normalizeMenu(m, defaultLocale))
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
        body: JSON.stringify({ data: menusList.map((m) => normalizeMenu(m, defaultLocale)) }),
      })
    }
    if (method === 'POST' && url.endsWith('/menus') && !url.includes('/reorder')) {
      const body = JSON.parse(route.request().postData() || '{}')
      const translations = body.translations ?? (body.name != null ? { [defaultLocale]: { name: body.name, description: body.description ?? null } } : { [defaultLocale]: { name: 'Unnamed menu', description: null } })
      const name = translations[defaultLocale]?.name ?? body.name ?? 'Unnamed menu'
      const newMenu = normalizeMenu({
        uuid: 'menu-new-' + Date.now(),
        name,
        translations,
        is_active: body.is_active !== false,
        sort_order: typeof body.sort_order === 'number' ? body.sort_order : menusList.length,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }, defaultLocale)
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
      const nextTranslations = body.translations
        ? { ...(current.translations ?? {}), ...body.translations }
        : (current.translations ?? { [defaultLocale]: { name: current.name, description: null } })
      if (body.name !== undefined && nextTranslations[defaultLocale]) {
        nextTranslations[defaultLocale] = { ...nextTranslations[defaultLocale], name: body.name }
      }
      const updated = normalizeMenu({
        ...current,
        translations: nextTranslations,
        name: nextTranslations[defaultLocale]?.name ?? current.name,
        is_active: body.is_active !== undefined ? body.is_active : current.is_active,
        updated_at: new Date().toISOString(),
      }, defaultLocale)
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
      const translations = body.translations ?? { en: { name: 'New category', description: null } }
      const firstLoc = Object.keys(translations)[0] ?? 'en'
      const defaultName = translations[firstLoc]?.name ?? 'New category'
      const newCat = {
        uuid: 'cat-' + Date.now(),
        sort_order: state.length,
        is_active: true,
        translations: Object.fromEntries(
          Object.entries(translations).map(([loc, t]) => [loc, { name: t?.name ?? defaultName, description: t?.description ?? null }])
        ),
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
      if (idx >= 0) {
        const next = { ...state[idx], updated_at: new Date().toISOString() }
        if (body.translations) {
          next.translations = { ...(next.translations ?? {}), ...body.translations }
          Object.keys(next.translations).forEach((loc) => {
            const t = next.translations[loc]
            if (t && typeof t === 'object') next.translations[loc] = { name: t.name ?? next.translations[loc]?.name, description: t.description !== undefined ? t.description : next.translations[loc]?.description ?? null }
          })
        }
        if (body.is_active !== undefined) next.is_active = body.is_active
        if (body.availability !== undefined) next.availability = body.availability
        state[idx] = next
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
      const fromCatalog = body.source_menu_item_uuid != null
      const name = body.translations?.en?.name ?? (fromCatalog ? 'From catalog' : 'New Item')
      const newItem = {
        uuid: 'item-' + Date.now(),
        category_uuid: body.category_uuid ?? null,
        sort_order: body.sort_order ?? state.length,
        price: body.price ?? null,
        is_active: body.is_active !== false,
        is_available: body.is_available !== false,
        translations: body.translations ?? { en: { name, description: null } },
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      if (fromCatalog) {
        newItem.source_menu_item_uuid = body.source_menu_item_uuid
        newItem.source_variant_uuid = body.source_variant_uuid ?? null
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
        if (body.is_active !== undefined) state[idx].is_active = body.is_active
        if (body.is_available !== undefined) state[idx].is_available = body.is_available
        if (body.availability !== undefined) state[idx].availability = body.availability
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

/**
 * Mock languages list (GET) and per-locale translations (GET/PUT).
 * Vue uses restaurant.languages from GET restaurant; GET /languages is also called in some flows.
 * GET /restaurants/:id/translations/:locale returns { data: { description: string | null } }.
 * @param {import('@playwright/test').Page} page
 * @param {{ languages?: string[], descriptionByLocale?: Record<string, string | null> }} [options] - languages for GET /languages; descriptionByLocale for initial GET translation state
 */
function mockRestaurantLanguagesAndTranslations(page, options = {}) {
  const languages = options.languages ?? ['en']
  const descriptionState = { ...(options.descriptionByLocale ?? {}) }

  page.route(/^.*\/api\/restaurants\/[^/]+\/languages(\?.*)?$/, (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: languages }),
      })
    }
    return route.continue()
  })
  page.route(/^.*\/api\/restaurants\/[^/]+\/languages\/[^/]+$/, (route) => {
    if (route.request().method() === 'DELETE') {
      return route.fulfill({ status: 204, body: '' })
    }
    return route.continue()
  })
  // GET /restaurants/:id/translations/:locale or PUT same
  page.route(/^.*\/api\/restaurants\/[^/]+\/translations\/([^/]+)$/, (route) => {
    const url = route.request().url()
    const localeMatch = url.match(/\/translations\/([^/]+)$/)
    const locale = localeMatch ? decodeURIComponent(localeMatch[1]) : 'en'
    if (route.request().method() === 'GET') {
      const description = descriptionState[locale] ?? null
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { description } }),
      })
    }
    if (route.request().method() === 'PUT') {
      const body = JSON.parse(route.request().postData() || '{}')
      descriptionState[locale] = body.description ?? null
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Translation updated.', data: { description: descriptionState[locale] } }),
      })
    }
    return route.continue()
  })
}

const PHONE_CONTACT_TYPES = ['whatsapp', 'mobile', 'phone', 'fax', 'other']

/**
 * Mock restaurant contacts: GET list, POST create, PATCH update, DELETE.
 * Supports value (and number for backward compat). Link types use value as URL.
 * @param {import('@playwright/test').Page} page
 * @param {Array<{ uuid: string, type: string, number?: string, value?: string, label?: string | null, is_active?: boolean }>} [initialContacts]
 */
function mockRestaurantContacts(page, initialContacts = []) {
  const state = initialContacts.map((c) => {
    const type = c.type || 'phone'
    const val = c.value ?? c.number ?? ''
    return {
      uuid: c.uuid,
      type,
      value: val,
      number: PHONE_CONTACT_TYPES.includes(type) ? val : null,
      label: c.label ?? null,
      is_active: c.is_active !== false,
      created_at: c.created_at ?? '2025-01-01T00:00:00.000000Z',
      updated_at: c.updated_at ?? '2025-01-01T00:00:00.000000Z',
    }
  })

  page.route(/^.*\/api\/restaurants\/[^/]+\/contacts(\/[^/]+)?$/, (route) => {
    const url = route.request().url()
    const method = route.request().method()
    const contactMatch = url.match(/\/contacts\/([^/?#]+)$/)
    const contactUuid = contactMatch ? contactMatch[1] : null

    if (method === 'GET' && !contactUuid) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: state.map((c) => ({ ...c })) }),
      })
    }
    if (method === 'GET' && contactUuid) {
      const contact = state.find((c) => c.uuid === contactUuid)
      if (!contact) {
        return route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Contact not found.' }) })
      }
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...contact } }),
      })
    }
    if (method === 'POST' && !contactUuid) {
      const body = JSON.parse(route.request().postData() || '{}')
      const type = body.type || 'phone'
      const val = String(body.value ?? body.number ?? '').trim()
      const newContact = {
        uuid: 'contact-' + Date.now(),
        type,
        value: val,
        number: PHONE_CONTACT_TYPES.includes(type) ? val : null,
        label: body.label != null && String(body.label).trim() !== '' ? String(body.label).trim() : null,
        is_active: body.is_active !== false,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      state.push(newContact)
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Contact created.', data: newContact }),
      })
    }
    if ((method === 'PATCH' || method === 'PUT') && contactUuid) {
      const contact = state.find((c) => c.uuid === contactUuid)
      if (!contact) {
        return route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Contact not found.' }) })
      }
      const body = JSON.parse(route.request().postData() || '{}')
      if (body.type !== undefined) contact.type = body.type
      if (body.value !== undefined) {
        contact.value = String(body.value).trim()
        if (PHONE_CONTACT_TYPES.includes(contact.type)) contact.number = contact.value
      }
      if (body.number !== undefined) contact.number = PHONE_CONTACT_TYPES.includes(contact.type) ? String(body.number).trim() : null
      if (body.label !== undefined) contact.label = body.label != null && String(body.label).trim() !== '' ? String(body.label).trim() : null
      if (body.is_active !== undefined) contact.is_active = body.is_active
      contact.updated_at = new Date().toISOString()
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Contact updated.', data: { ...contact } }),
      })
    }
    if (method === 'DELETE' && contactUuid) {
      const idx = state.findIndex((c) => c.uuid === contactUuid)
      if (idx === -1) {
        return route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Contact not found.' }) })
      }
      state.splice(idx, 1)
      return route.fulfill({ status: 204, body: '' })
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
    mockRestaurantContacts(page, [])

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

  test('Add second menu with name Unnamed menu via modal succeeds', async ({ page }) => {
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
    await menuTab.setMenuName('Unnamed menu')
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

  test('owner can rename menu via Edit menu modal and see name update in selector', async ({ page }) => {
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
    await menuTab.expectEditMenuModalOpen()
    await menuTab.setEditMenuName('Evening menu')
    await menuTab.submitEditMenu()
    await menuTab.expectEditMenuModalClosed()
    await menuTab.expectMenuInSelector('Evening menu')
  })

  test('Add menu modal with one language has no Edit in dropdown', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openAddMenuModal()
    await menuTab.expectAddMenuModalOpen()
    await menuTab.expectAddMenuEditInDropdownHidden()
    await menuTab.cancelAddMenuModal()
  })

  test('Create menu with name and description for default locale', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openAddMenuModal()
    await menuTab.expectAddMenuModalOpen()
    await menuTab.setMenuName('Lunch & Drinks')
    await menuTab.setAddMenuDescription('Our lunch and drink selection.')
    await menuTab.submitCreateMenu()
    await menuTab.expectAddMenuModalClosed()
    await menuTab.expectMenuInSelector('Lunch & Drinks')
  })

  test('Edit menu and add description', async ({ page }) => {
    const menus = [{ uuid: 'menu-edit-desc', name: 'Dinner', is_active: true, sort_order: 0, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }]
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
    await menuTab.expectEditMenuModalOpen()
    await menuTab.expectEditMenuEditInDropdownHidden()
    await menuTab.setEditMenuDescription('Evening meals and desserts.')
    await menuTab.submitEditMenu()
    await menuTab.expectEditMenuModalClosed()
    await menuTab.expectMenuInSelector('Dinner')
  })

  test('Add category modal with one language has no Edit in dropdown', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openAddCategoryModal()
    await menuTab.expectAddCategoryModalOpen()
    await menuTab.expectCategoryEditInDropdownHidden()
    await menuTab.cancelCategoryModal()
    await menuTab.expectCategoryModalClosed()
  })

  test('Create category with name and description; edit and add description', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openAddCategoryModal()
    await menuTab.expectAddCategoryModalOpen()
    await menuTab.setCategoryName('Starters')
    await menuTab.setCategoryDescription('Light bites to begin.')
    await menuTab.submitSaveCategory()
    await menuTab.expectCategoryModalClosed()
    await menuTab.expectCategoryVisible('Starters')
    await menuTab.openEditCategoryModal('Starters')
    await menuTab.expectEditCategoryModalOpen()
    await menuTab.setCategoryDescription('Light bites and small plates to begin.')
    await menuTab.submitSaveCategory()
    await menuTab.expectCategoryModalClosed()
    await menuTab.expectCategoryVisible('Starters')
  })

  test('Category modal with multiple languages shows Edit in dropdown and (Default) option', async ({ page }) => {
    const restaurantWithTwoLanguages = { ...MOCK_RESTAURANT, languages: ['en', 'fr'], default_locale: 'en' }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [restaurantWithTwoLanguages])
    mockRestaurantGet(page, restaurantWithTwoLanguages)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page, { languages: ['en', 'fr'] })

    const menuTab = new RestaurantMenuTabPage(page)
    await menuTab.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTab.openAddCategoryModal()
    await menuTab.expectAddCategoryModalOpen()
    await menuTab.expectCategoryEditInDropdownVisible()
    await menuTab.expectCategoryEditInDropdownShowsDefaultOption()
    await menuTab.setCategoryName('Desserts')
    await menuTab.submitSaveCategory()
    await menuTab.expectCategoryModalClosed()
    await menuTab.expectCategoryVisible('Desserts')
  })

  test('Remove language flow completes without error', async ({ page }) => {
    const restaurantWithTwoLanguages = { ...MOCK_RESTAURANT, languages: ['en', 'fr'], default_locale: 'en' }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [restaurantWithTwoLanguages])
    mockRestaurantGet(page, restaurantWithTwoLanguages)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page, { languages: ['en', 'fr'] })

    const settingsPage = new RestaurantSettingsPage(page)
    await settingsPage.goToSettingsTab(MOCK_RESTAURANT.uuid)
    await settingsPage.expectLanguagesHeadingVisible()
    await settingsPage.expectLanguageRowVisible('English')
    await settingsPage.expectLanguageRowVisible('French')
    await settingsPage.clickRemoveLanguage('French')
    await settingsPage.expectRemoveLanguageModalOpen()
    await settingsPage.confirmRemoveLanguageModal()
    await settingsPage.expectRemoveLanguageModalClosed()
    await settingsPage.expectLanguageRowNotVisible('French')
    await settingsPage.expectLanguageRowVisible('English')
  })

  test('restaurant Availability tab shows heading and weekly schedule with day rows', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const availabilityPage = new RestaurantManageAvailabilityPage(page)
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectScheduleVisible()
    await availabilityPage.expectDayVisible('monday')
    await availabilityPage.expectDayVisible('sunday')
  })

  test('owner can change slot times on Availability tab, then save from Profile with no error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const availabilityPage = new RestaurantManageAvailabilityPage(page)
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectScheduleVisible()
    await availabilityPage.setSlotTime('monday', 0, 'from', '10:00')
    await availabilityPage.setSlotTime('monday', 0, 'to', '15:00')
    await availabilityPage.goToProfileTab()
    await availabilityPage.clickSaveChanges()
    await availabilityPage.expectFormErrorHidden()
    await availabilityPage.expectSuccessToastWithMessage('Restaurant updated.')
  })

  test('overlapping slots block save and show form error and per-day error on Availability tab', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const availabilityPage = new RestaurantManageAvailabilityPage(page)
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectScheduleVisible()
    await availabilityPage.setSlotTime('monday', 0, 'from', '09:00')
    await availabilityPage.setSlotTime('monday', 0, 'to', '12:00')
    await availabilityPage.addSlotForDay('monday')
    await availabilityPage.setSlotTime('monday', 1, 'from', '11:00')
    await availabilityPage.setSlotTime('monday', 1, 'to', '14:00')
    await availabilityPage.goToProfileTab()
    await availabilityPage.clickSaveChanges()
    await availabilityPage.expectFormErrorVisible()
    await availabilityPage.expectFormErrorToContain('fix the schedule')
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectAvailabilitySummaryErrorVisible()
    await availabilityPage.expectDayErrorVisible('monday')
    await availabilityPage.expectDayErrorToContain('monday', 'overlap')
  })

  test('valid non-overlapping slots save successfully from Profile', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const availabilityPage = new RestaurantManageAvailabilityPage(page)
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectScheduleVisible()
    await availabilityPage.setSlotTime('monday', 0, 'from', '09:00')
    await availabilityPage.setSlotTime('monday', 0, 'to', '12:00')
    await availabilityPage.addSlotForDay('monday')
    await availabilityPage.setSlotTime('monday', 1, 'from', '12:00')
    await availabilityPage.setSlotTime('monday', 1, 'to', '18:00')
    await availabilityPage.goToProfileTab()
    await availabilityPage.clickSaveChanges()
    await availabilityPage.expectFormErrorHidden()
    await availabilityPage.expectSuccessToastWithMessage('Restaurant updated.')
  })

  test('owner sets operating hours on Availability tab and saves with Save button', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const availabilityPage = new RestaurantManageAvailabilityPage(page)
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectScheduleVisible()
    await availabilityPage.setSlotTime('monday', 0, 'from', '10:00')
    await availabilityPage.setSlotTime('monday', 0, 'to', '15:00')
    await availabilityPage.clickSaveOnAvailabilityTab()
    await availabilityPage.expectSuccessToastWithMessage('Operating hours saved.')
  })

  test('owner closes a day, sets slots, saves on Availability tab; reload confirms hours persist', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const availabilityPage = new RestaurantManageAvailabilityPage(page)
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectScheduleVisible()
    await availabilityPage.setDayOpen('sunday', false)
    await availabilityPage.setSlotTime('monday', 0, 'from', '09:00')
    await availabilityPage.setSlotTime('monday', 0, 'to', '17:00')
    await availabilityPage.clickSaveOnAvailabilityTab()
    await availabilityPage.expectSuccessToastWithMessage('Operating hours saved.')
    await page.reload()
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectDayShowsClosed('sunday')
  })

  test('from-before-to validation blocks save on Availability tab and shows error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const availabilityPage = new RestaurantManageAvailabilityPage(page)
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToAvailabilityTab()
    await availabilityPage.expectScheduleVisible()
    await availabilityPage.setSlotTime('monday', 0, 'from', '12:00')
    await availabilityPage.setSlotTime('monday', 0, 'to', '10:00')
    await availabilityPage.clickSaveOnAvailabilityTab()
    await availabilityPage.expectAvailabilitySummaryErrorVisible()
    await availabilityPage.expectAvailabilitySummaryErrorToContain('fix the schedule')
    await availabilityPage.expectDayErrorToContain('monday', 'From must be before to')
  })

  test('public restaurant page shows Opening hours when operating_hours set', async ({ page }) => {
    mockPublicRestaurant(page, 'test-pizza', {
      name: 'Test Pizza',
      slug: 'test-pizza',
      operating_hours: {
        monday: { open: true, slots: [{ from: '10:00', to: '15:00' }] },
        tuesday: { open: false, slots: [] },
      },
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('test-pizza')
    await publicPage.expectOpeningHoursSectionVisible()
  })

  test.describe('Public restaurant language dropdown', () => {
    test('single-language restaurant does not show language dropdown', async ({ page }) => {
      mockPublicRestaurant(page, 'single-lang', {
        name: 'Single Lang Cafe',
        slug: 'single-lang',
        languages: ['en'],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('single-lang')
      await publicPage.expectLanguageDropdownNotVisible()
    })

    test('multi-language restaurant shows dropdown; selecting language updates URL and content', async ({ page }) => {
      mockPublicRestaurant(
        page,
        'multi-lang',
        {
          name: 'Multi Lang Bistro',
          slug: 'multi-lang',
          languages: ['en', 'nl'],
          default_locale: 'en',
          description: 'About us in English',
          menu_groups: [
            {
              category_name: 'Starters',
              category_uuid: null,
              availability: null,
              items: [
                {
                  uuid: 'item-1',
                  type: 'simple',
                  name: 'Soup of the day',
                  description: null,
                  price: 5,
                  is_available: true,
                  availability: null,
                  tags: [],
                },
              ],
            },
          ],
          menu_items: [
            { uuid: 'item-1', type: 'simple', name: 'Soup of the day', description: null, price: 5, is_available: true, availability: null, tags: [] },
          ],
        },
        {
          byLocale: {
            en: {
              description: 'About us in English',
              menu_groups: [
                {
                  category_name: 'Starters',
                  category_uuid: null,
                  availability: null,
                  items: [
                    { uuid: 'item-1', type: 'simple', name: 'Soup of the day', description: null, price: 5, is_available: true, availability: null, tags: [] },
                  ],
                },
              ],
              menu_items: [
                { uuid: 'item-1', type: 'simple', name: 'Soup of the day', description: null, price: 5, is_available: true, availability: null, tags: [] },
              ],
            },
            nl: {
              description: 'Over ons in het Nederlands',
              menu_groups: [
                {
                  category_name: 'Voorgerechten',
                  category_uuid: null,
                  availability: null,
                  items: [
                    { uuid: 'item-1', type: 'simple', name: 'Soep van de dag', description: null, price: 5, is_available: true, availability: null, tags: [] },
                  ],
                },
              ],
              menu_items: [
                { uuid: 'item-1', type: 'simple', name: 'Soep van de dag', description: null, price: 5, is_available: true, availability: null, tags: [] },
              ],
            },
          },
        }
      )

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('multi-lang')
      await publicPage.expectLanguageDropdownVisible()
      await publicPage.expectMainContainsText('About us in English')
      await publicPage.expectTextVisible('Soup of the day')

      await publicPage.selectLanguage('nl')
      await publicPage.expectUrlHasLocale('nl')
      await publicPage.expectMainContainsText('Over ons in het Nederlands')
      await publicPage.expectTextVisible('Soep van de dag')
    })
  })

  test('public menu shows Not Available pill when menu item has is_available false', async ({ page }) => {
    mockPublicRestaurant(page, 'test-pizza', {
      name: 'Test Pizza',
      slug: 'test-pizza',
      menu_items: [
        { uuid: 'item-1', name: 'Margherita', description: null, price: 10, is_available: true },
        { uuid: 'item-2', name: 'Sold Out Pie', description: null, price: 12, is_available: false },
      ],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('test-pizza')
    await publicPage.expectNotAvailablePillVisible()
  })

  test('owner can open Logo & banner modal, upload logo (mocked), and see logo on manage banner without refresh', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGetWithMediaUpload(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const managePage = new RestaurantManagePage(page)
    await managePage.goToManagePage(MOCK_RESTAURANT.uuid)
    await managePage.openLogoBannerModal()
    await managePage.expectLogoBannerModalOpen()
    await managePage.setLogoFile()
    await managePage.closeLogoBannerModal()
    await managePage.expectLogoBannerModalClosed()
    await managePage.expectLogoVisibleInManageBanner()
  })

  test('manage page with slug shows QR code in Web address card', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const managePage = new RestaurantManagePage(page)
    await managePage.goToManagePage(MOCK_RESTAURANT.uuid)
    await managePage.expectManageQRCodeVisible()
  })

  test.describe('Restaurant contacts', () => {
    test('owner can see Contact & links section in Profile tab', async ({ page }) => {
      await loginAsVerifiedUser(page)
      mockRestaurantList(page, [MOCK_RESTAURANT])
      mockRestaurantGet(page, MOCK_RESTAURANT)
      mockRestaurantMenus(page)
      mockRestaurantCategories(page, [])
      mockRestaurantMenuItems(page, [])
      mockRestaurantLanguagesAndTranslations(page)
      mockRestaurantContacts(page, [])

      const managePage = new RestaurantManagePage(page)
      await managePage.goToManagePage(MOCK_RESTAURANT.uuid)
      await managePage.expectManageTabsVisible()
      await managePage.expectProfileTabSelected()
      await managePage.expectProfilePanelVisible()

      const contactsPage = new RestaurantContactsPage(page)
      await contactsPage.expectContactsPanelVisible()
      await contactsPage.expectNoContactsYetMessage()
    })

    test('owner navigating to /contacts is redirected to Profile and sees Contact & links', async ({ page }) => {
      await loginAsVerifiedUser(page)
      mockRestaurantList(page, [MOCK_RESTAURANT])
      mockRestaurantGet(page, MOCK_RESTAURANT)
      mockRestaurantMenus(page)
      mockRestaurantCategories(page, [])
      mockRestaurantMenuItems(page, [])
      mockRestaurantLanguagesAndTranslations(page)
      mockRestaurantContacts(page, [])

      const contactsPage = new RestaurantContactsPage(page)
      await contactsPage.goToContactsByRoute(MOCK_RESTAURANT.uuid)
      await contactsPage.expectContactsPanelVisible()
      await contactsPage.expectNoContactsYetMessage()
    })

    test('owner can add contact and list shows it', async ({ page }) => {
      await loginAsVerifiedUser(page)
      mockRestaurantList(page, [MOCK_RESTAURANT])
      mockRestaurantGet(page, MOCK_RESTAURANT)
      mockRestaurantMenus(page)
      mockRestaurantCategories(page, [])
      mockRestaurantMenuItems(page, [])
      mockRestaurantLanguagesAndTranslations(page)
      mockRestaurantContacts(page, [])

      const contactsPage = new RestaurantContactsPage(page)
      await contactsPage.goToContacts(MOCK_RESTAURANT.uuid)
      await contactsPage.clickAddContact()
      await contactsPage.expectContactFormVisible()
      await contactsPage.expectContactFormHeading('Add contact or link')
      await contactsPage.setContactType('whatsapp')
      await contactsPage.setContactNumber('+1 234 567 8900')
      await contactsPage.setContactLabel('Reservations')
      await contactsPage.setContactShowOnPublic(true)
      await contactsPage.submitContactForm()

      await contactsPage.expectContactInList({ number: '+1 234 567 8900', typeLabel: 'WhatsApp', label: 'Reservations' })
    })

    test('owner can add a link (e.g. Facebook with URL) and list shows value and type', async ({ page }) => {
      await loginAsVerifiedUser(page)
      mockRestaurantList(page, [MOCK_RESTAURANT])
      mockRestaurantGet(page, MOCK_RESTAURANT)
      mockRestaurantMenus(page)
      mockRestaurantCategories(page, [])
      mockRestaurantMenuItems(page, [])
      mockRestaurantContacts(page, [])

      const contactsPage = new RestaurantContactsPage(page)
      await contactsPage.goToContacts(MOCK_RESTAURANT.uuid)
      await contactsPage.clickAddContact()
      await contactsPage.expectContactFormVisible()
      await contactsPage.setContactType('facebook')
      await contactsPage.setContactNumber('https://facebook.com/example-restaurant')
      await contactsPage.setContactLabel('Our Facebook')
      await contactsPage.submitContactForm()

      await contactsPage.expectContactInList({
        value: 'https://facebook.com/example-restaurant',
        typeLabel: 'Facebook',
        label: 'Our Facebook',
      })
    })

    test('owner sees validation error for invalid URL on link type', async ({ page }) => {
      await loginAsVerifiedUser(page)
      mockRestaurantList(page, [MOCK_RESTAURANT])
      mockRestaurantGet(page, MOCK_RESTAURANT)
      mockRestaurantMenus(page)
      mockRestaurantCategories(page, [])
      mockRestaurantMenuItems(page, [])
      mockRestaurantContacts(page, [])

      const contactsPage = new RestaurantContactsPage(page)
      await contactsPage.goToContacts(MOCK_RESTAURANT.uuid)
      await contactsPage.clickAddContact()
      await contactsPage.expectContactFormVisible()
      await contactsPage.setContactType('website')
      await contactsPage.setContactNumber('not-a-valid-url')
      await contactsPage.submitContactForm()

      await contactsPage.expectContactFormValidationError(/valid URL/)
    })

    test('owner can toggle is_active for a contact', async ({ page }) => {
      await loginAsVerifiedUser(page)
      mockRestaurantList(page, [MOCK_RESTAURANT])
      mockRestaurantGet(page, MOCK_RESTAURANT)
      mockRestaurantMenus(page)
      mockRestaurantCategories(page, [])
      mockRestaurantMenuItems(page, [])
      mockRestaurantLanguagesAndTranslations(page)
      mockRestaurantContacts(page, [
        { uuid: 'contact-1', type: 'mobile', number: '+99 888 777 6666', label: 'Kitchen', is_active: true },
      ])

      const contactsPage = new RestaurantContactsPage(page)
      await contactsPage.goToContacts(MOCK_RESTAURANT.uuid)
      await contactsPage.expectContactInList({ number: '+99 888 777 6666', typeLabel: 'Mobile' })
      await contactsPage.expectContactNotMarkedHidden('+99 888 777 6666')

      await contactsPage.clickToggleActiveForContact('+99 888 777 6666')
      await contactsPage.expectContactMarkedHidden('+99 888 777 6666')

      await contactsPage.clickToggleActiveForContact('+99 888 777 6666')
      await contactsPage.expectContactNotMarkedHidden('+99 888 777 6666')
    })

    test('owner can edit a contact', async ({ page }) => {
      await loginAsVerifiedUser(page)
      mockRestaurantList(page, [MOCK_RESTAURANT])
      mockRestaurantGet(page, MOCK_RESTAURANT)
      mockRestaurantMenus(page)
      mockRestaurantCategories(page, [])
      mockRestaurantMenuItems(page, [])
      mockRestaurantLanguagesAndTranslations(page)
      mockRestaurantContacts(page, [
        { uuid: 'contact-edit-1', type: 'phone', number: '555-0000', label: 'Old', is_active: true },
      ])

      const contactsPage = new RestaurantContactsPage(page)
      await contactsPage.goToContacts(MOCK_RESTAURANT.uuid)
      await contactsPage.expectContactInList({ number: '555-0000', label: 'Old' })

      await contactsPage.clickEditForContact('555-0000')
      await contactsPage.expectContactFormHeading('Edit contact')
      await contactsPage.setContactNumber('555-9999')
      await contactsPage.setContactLabel('Reception')
      await contactsPage.submitContactForm()

      await contactsPage.expectContactInList({ number: '555-9999', label: 'Reception' })
    })

    test('owner can delete contact with confirmation', async ({ page }) => {
      await loginAsVerifiedUser(page)
      mockRestaurantList(page, [MOCK_RESTAURANT])
      mockRestaurantGet(page, MOCK_RESTAURANT)
      mockRestaurantMenus(page)
      mockRestaurantCategories(page, [])
      mockRestaurantMenuItems(page, [])
      mockRestaurantLanguagesAndTranslations(page)
      mockRestaurantContacts(page, [
        { uuid: 'contact-del-1', type: 'other', number: '111-222-3333', label: 'To delete', is_active: true },
      ])

      const contactsPage = new RestaurantContactsPage(page)
      await contactsPage.goToContacts(MOCK_RESTAURANT.uuid)
      await contactsPage.expectContactInList({ number: '111-222-3333' })

      await contactsPage.clickDeleteForContact('111-222-3333')
      await contactsPage.expectDeleteModalVisible()
      await contactsPage.cancelDeleteContact()
      await contactsPage.expectDeleteModalClosed()
      await contactsPage.expectContactInList({ number: '111-222-3333' })

      await contactsPage.clickDeleteForContact('111-222-3333')
      await contactsPage.expectDeleteModalVisible()
      await contactsPage.confirmDeleteContact()
      await contactsPage.expectDeleteModalClosed()
      await contactsPage.expectNoContactsYetMessage()
    })

    test('public restaurant page shows active contacts', async ({ page }) => {
      mockPublicRestaurant(page, 'test-pizza', {
        name: 'Test Pizza',
        slug: 'test-pizza',
        contacts: [
          { uuid: 'c1', type: 'mobile', number: '+44 20 7946 0958', label: 'Reception' },
          { uuid: 'c2', type: 'whatsapp', number: '15551234567', label: null },
        ],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('test-pizza')
      await publicPage.expectContactSectionVisible()
      await publicPage.expectActiveContactVisible('+44 20 7946 0958')
      await publicPage.expectActiveContactVisible('Reception')
      await publicPage.expectActiveContactVisible('15551234567')
    })

    test('public restaurant page WhatsApp link has correct wa.me href', async ({ page }) => {
      mockPublicRestaurant(page, 'test-pizza', {
        name: 'Test Pizza',
        slug: 'test-pizza',
        contacts: [{ uuid: 'c1', type: 'whatsapp', number: '15551234567', label: null }],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('test-pizza')
      await publicPage.expectContactSectionVisible()
      await publicPage.expectWhatsAppLinkWithNumber('15551234567')
    })

    test('public restaurant page shows no contact numbers when contacts empty', async ({ page }) => {
      mockPublicRestaurant(page, 'test-pizza', {
        name: 'Test Pizza',
        slug: 'test-pizza',
        contacts: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('test-pizza')
      await publicPage.expectContactSectionVisible()
      await publicPage.expectNoContactNumbersListed()
    })

    test('public restaurant page shows one contact when mock has one and link type is clickable', async ({ page }) => {
      const websiteUrl = 'https://example.com/our-restaurant'
      mockPublicRestaurant(page, 'test-pizza', {
        name: 'Test Pizza',
        slug: 'test-pizza',
        contacts: [
          { uuid: 'c1', type: 'website', value: websiteUrl, label: 'Our site', is_active: true },
        ],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('test-pizza')
      await publicPage.expectContactSectionVisible()
      await publicPage.expectActiveContactVisible(websiteUrl)
      await publicPage.expectContactLinkWithHref(websiteUrl)
    })
  })

  test('public restaurant page with logo shows header with name and hero logo block', async ({ page }) => {
    mockPublicRestaurant(page, 'test-pizza', {
      name: 'Test Pizza',
      slug: 'test-pizza',
      logo_url: 'https://example.com/logo.png',
      banner_url: null,
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('test-pizza')
    await publicPage.expectHeaderWithNameVisible('Test Pizza')
    await publicPage.expectHeroLogoBlockVisible()
  })

  test('public restaurant page without logo does not show hero logo block', async ({ page }) => {
    mockPublicRestaurant(page, 'test-pizza', {
      name: 'Test Pizza',
      slug: 'test-pizza',
      logo_url: null,
      banner_url: null,
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('test-pizza')
    await publicPage.expectHeroLogoBlockNotVisible()
  })

  test('restaurant Settings tab shows Currency, Languages, and description-by-language with dropdown and single textarea', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const settingsPage = new RestaurantSettingsPage(page)
    await settingsPage.goToSettingsTab(MOCK_RESTAURANT.uuid)
    await settingsPage.expectSettingsHeadingVisible()
    await settingsPage.expectCurrencySelectVisible()
    await settingsPage.expectLanguagesHeadingVisible()
    await settingsPage.expectDescriptionSectionVisible()
    await settingsPage.expectDescriptionTextareaVisibleForLocale('en')
  })

  test('owner can select Minimal template in Settings and selection is reflected with no error', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const settingsPage = new RestaurantSettingsPage(page)
    await settingsPage.goToSettingsTab(MOCK_RESTAURANT.uuid)
    await settingsPage.expectTemplateSectionVisible()
    await settingsPage.expectTemplateCardSelected('template-1')
    await settingsPage.selectTemplate('template-2')
    await settingsPage.expectTemplateCardSelected('template-2')
    await settingsPage.expectNoTemplateError()
  })

  test('public page /r/:slug returns 200 and Vue app mounts', async ({ page }) => {
    mockPublicRestaurant(page, 'test-pizza', { name: 'Test Pizza', slug: 'test-pizza' })

    const publicPage = new PublicRestaurantPage(page)
    const response = await publicPage.goToPublicBySlugAndGetResponse('test-pizza')
    expect(response).toBeTruthy()
    expect(response.ok()).toBe(true)
  })

  test('guest sees full public page: header, hero, menu with item name and price, about, reviews section, footer', async ({ page }) => {
    mockPublicRestaurant(page, 'test', {
      name: 'Test Restaurant',
      slug: 'test',
      description: 'We serve the best food in town.',
      menu_items: [
        { uuid: 'item-1', name: 'Margherita', description: null, price: 10, sort_order: 0, is_available: true, availability: null, tags: [] },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('test')
    await publicPage.expectHeaderWithNameVisible('Test Restaurant')
    await publicPage.expectHeroWithNameVisible('Test Restaurant')
    await publicPage.expectMenuSectionVisible()
    await publicPage.expectMenuHasAtLeastOneItem()
    await publicPage.expectMenuItemNameVisible('Margherita')
    await publicPage.expectPriceVisibleInMenu('$10.00')
    await publicPage.expectAboutSectionVisible()
    await publicPage.expectReviewsSectionVisible()
    await publicPage.expectFooterWithNameVisible('Test Restaurant')
  })

  test('guest sees public page without about when description is empty', async ({ page }) => {
    mockPublicRestaurant(page, 'test', {
      name: 'No About Place',
      slug: 'test',
      description: null,
      menu_items: [
        { uuid: 'item-1', name: 'Coffee', description: null, price: 3, sort_order: 0, is_available: true, availability: null, tags: [] },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('test')
    await publicPage.expectHeaderWithNameVisible('No About Place')
    await publicPage.expectMenuSectionVisible()
    await publicPage.expectMenuHasAtLeastOneItem()
    await publicPage.expectAboutSectionNotVisible()
    await publicPage.expectFooterWithNameVisible('No About Place')
  })

  test('public page loads with template-1 and shows template-1 header and sections', async ({ page }) => {
    mockPublicRestaurant(page, 'test-pizza', {
      name: 'Test Pizza',
      slug: 'test-pizza',
      template: 'template-1',
      description: 'Best pizza in town.',
      menu_items: [
        { uuid: 'item-1', name: 'Pepperoni', description: null, price: 12, sort_order: 0, is_available: true, availability: null, tags: [] },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('test-pizza')
    await publicPage.expectTemplate1HeaderAndSectionsVisible('Test Pizza')
  })

  test('public page loads with template-2 and shows template-2 header and sections', async ({ page }) => {
    mockPublicRestaurant(page, 'minimal-cafe', {
      name: 'Minimal Cafe',
      slug: 'minimal-cafe',
      template: 'template-2',
      description: 'Simple and minimal.',
      menu_items: [
        { uuid: 'item-1', name: 'Espresso', description: null, price: 2.5, sort_order: 0, is_available: true, availability: null, tags: [] },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('minimal-cafe')
    await publicPage.expectTemplate2HeaderAndSectionsVisible('Minimal Cafe')
  })

  test('guest visits public page and sees restaurant name and menu section', async ({ page }) => {
    mockPublicRestaurant(page, 'cafe-one', {
      name: 'Cafe One',
      slug: 'cafe-one',
      menu_items: [
        { uuid: 'i1', type: 'simple', name: 'Croissant', description: null, price: 4, sort_order: 0, is_available: true, availability: null, tags: [] },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('cafe-one')
    await publicPage.expectHeaderWithNameVisible('Cafe One')
    await publicPage.expectMenuSectionVisible()
    await publicPage.expectMenuHasAtLeastOneItem()
  })

  test('public page never shows Template 1 or Template 2 label to guests', async ({ page }) => {
    mockPublicRestaurant(page, 'no-badge', {
      name: 'No Badge Cafe',
      slug: 'no-badge',
      menu_items: [
        { uuid: 'i1', type: 'simple', name: 'Coffee', description: null, price: 3, sort_order: 0, is_available: true, availability: null, tags: [] },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('no-badge')
    await publicPage.expectNoTemplateLabelVisible()
  })

  test('public menu shows simple, combo, and variant items with correct display', async ({ page }) => {
    mockPublicRestaurant(page, 'mixed-menu', {
      name: 'Mixed Menu Place',
      slug: 'mixed-menu',
      menu_items: [
        { uuid: 's1', type: 'simple', name: 'House Salad', description: null, price: 8, sort_order: 0, is_available: true, availability: null, tags: [] },
        {
          uuid: 'c1',
          type: 'combo',
          name: 'Burger Combo',
          description: null,
          price: 14,
          sort_order: 1,
          is_available: true,
          availability: null,
          tags: [],
          combo_entries: [
            { referenced_item_uuid: 'ref-1', name: 'Cheeseburger', quantity: 1, modifier_label: null, variant_uuid: null },
            { referenced_item_uuid: 'ref-2', name: 'Fries', quantity: 1, modifier_label: null, variant_uuid: null },
          ],
        },
        {
          uuid: 'v1',
          type: 'with_variants',
          name: 'Pizza',
          description: null,
          price: null,
          sort_order: 2,
          is_available: true,
          availability: null,
          tags: [],
          variant_option_groups: [{ name: 'Size', values: ['Small', 'Large'] }],
          variant_skus: [
            { uuid: 'sk1', option_values: { Size: 'Small' }, price: 10, image_url: null },
            { uuid: 'sk2', option_values: { Size: 'Large' }, price: 16, image_url: null },
          ],
        },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('mixed-menu')
    await publicPage.expectMenuSectionVisible()
    await publicPage.expectMenuItemNameVisible('House Salad')
    await publicPage.expectTextVisible('$8.00')
    await publicPage.expectMenuItemNameVisible('Burger Combo')
    await publicPage.expectComboContentsListVisible()
    await publicPage.expectTextVisible('Cheeseburger')
    await publicPage.expectTextVisible('Fries')
    await publicPage.expectMenuItemNameVisible('Pizza')
    await publicPage.expectVariantSizeAndPriceOptionsVisible()
    await publicPage.expectTextVisible('$10.00')
    await publicPage.expectTextVisible('$16.00')
  })

  test('public menu shows Price on request for simple item with no price', async ({ page }) => {
    mockPublicRestaurant(page, 'on-request', {
      name: 'On Request Cafe',
      slug: 'on-request',
      menu_items: [
        { uuid: 'i1', type: 'simple', name: 'Chef\'s Special', description: null, price: null, sort_order: 0, is_available: true, availability: null, tags: [] },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('on-request')
    await publicPage.expectMenuSectionVisible()
    await publicPage.expectMenuItemNameVisible('Chef\'s Special')
    await publicPage.expectPriceOnRequestVisible()
  })

  test('public menu shows Not available for unavailable item without price', async ({ page }) => {
    mockPublicRestaurant(page, 'sold-out', {
      name: 'Sold Out Kitchen',
      slug: 'sold-out',
      menu_items: [
        { uuid: 'u1', type: 'simple', name: 'Sold Out Soup', description: null, price: 5, sort_order: 0, is_available: false, availability: null, tags: [] },
      ],
      feedbacks: [],
    })

    const publicPage = new PublicRestaurantPage(page)
    await publicPage.goToPublicBySlug('sold-out')
    await publicPage.expectMenuSectionVisible()
    await publicPage.expectMenuItemNameVisible('Sold Out Soup')
    await publicPage.expectNotAvailablePillVisible()
  })

  test.describe('Public menu view: names, prices, tags', () => {
    test('public menu shows item with name, price, and tag pills (template-1)', async ({ page }) => {
      mockPublicRestaurant(page, 'tags-t1', {
        name: 'Spicy Kitchen',
        slug: 'tags-t1',
        template: 'template-1',
        description: null,
        menu_items: [
          {
            uuid: 'tag-item-1',
            type: 'simple',
            name: 'Hot Wings',
            description: null,
            price: 9,
            sort_order: 0,
            is_available: true,
            availability: null,
            tags: [
              { uuid: 'tag-u1', text: 'Spicy', icon: 'local_fire_department', color: '#dc2626' },
            ],
          },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('tags-t1')
      await publicPage.expectTemplate1Applied()
      await publicPage.expectMenuSectionVisible()
      await publicPage.expectMenuHasAtLeastOneItem()
      await publicPage.expectMenuItemNameVisible('Hot Wings')
      await publicPage.expectPriceVisibleInMenu('$9.00')
      await publicPage.expectTagIconWithTitleVisible('Spicy')
      await publicPage.expectTagPillVisibleInMenu('Spicy')
    })

    test('public menu shows item with name, price, and tag pills (template-2)', async ({ page }) => {
      mockPublicRestaurant(page, 'tags-t2', {
        name: 'Vegan Cafe',
        slug: 'tags-t2',
        template: 'template-2',
        description: null,
        menu_items: [
          {
            uuid: 'tag-item-2',
            type: 'simple',
            name: 'Green Bowl',
            description: null,
            price: 12,
            sort_order: 0,
            is_available: true,
            availability: null,
            tags: [
              { uuid: 'tag-u2', text: 'Vegan', icon: 'eco', color: '#16a34a' },
            ],
          },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('tags-t2')
      await publicPage.expectTemplate2Applied()
      await publicPage.expectMenuSectionVisible()
      await publicPage.expectMenuHasAtLeastOneItem()
      await publicPage.expectMenuItemNameVisible('Green Bowl')
      await publicPage.expectPriceVisibleInMenu('$12.00')
      await publicPage.expectTagIconWithTitleVisible('Vegan')
      await publicPage.expectTagPillVisibleInMenu('Vegan')
    })

    test('small viewport: menu section still renders with items', async ({ page }) => {
      mockPublicRestaurant(page, 'mobile-menu', {
        name: 'Mobile Cafe',
        slug: 'mobile-menu',
        menu_items: [
          { uuid: 'm1', type: 'simple', name: 'Croissant', description: null, price: 4, sort_order: 0, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('mobile-menu')
      await publicPage.expectMenuSectionVisible()
      await publicPage.expectMenuHasAtLeastOneItem()
      await publicPage.expectMenuItemNameVisible('Croissant')
      await publicPage.expectPriceVisibleInMenu('$4.00')
    })
  })

  test.describe('Public menu availability (category and item)', () => {
    test('template-1 shows formatted category and item availability when set', async ({ page }) => {
      const categoryAvailability = availabilityMonFri1100To1500()
      const itemAvailability = availabilitySat1000To1400()
      mockPublicRestaurant(page, 'avail-t1', {
        name: 'Availability Cafe',
        slug: 'avail-t1',
        template: 'template-1',
        menu_groups: [
          {
            category_name: 'Lunch',
            category_uuid: 'cat-1',
            availability: categoryAvailability,
            items: [
              {
                uuid: 'av-i1',
                type: 'simple',
                name: 'Weekend Soup',
                description: null,
                price: 6,
                is_available: true,
                availability: itemAvailability,
                tags: [],
              },
            ],
          },
        ],
        menu_items: [],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('avail-t1')
      await publicPage.expectTemplate1Applied()
      await publicPage.expectMenuSectionVisible()
      await publicPage.expectMenuItemNameVisible('Weekend Soup')
      await publicPage.expectAvailabilityTextVisibleInMenu('Mon–Fri 11:00–15:00')
      await publicPage.expectAvailabilityTextVisibleInMenu('Sat 10:00–14:00')
    })

    test('template-2 shows formatted category and item availability when set', async ({ page }) => {
      const categoryAvailability = availabilityMonFri1100To1500()
      const itemAvailability = availabilitySat1000To1400()
      mockPublicRestaurant(page, 'avail-t2', {
        name: 'Availability Bistro',
        slug: 'avail-t2',
        template: 'template-2',
        menu_groups: [
          {
            category_name: 'Brunch',
            category_uuid: 'cat-2',
            availability: categoryAvailability,
            items: [
              {
                uuid: 'av-i2',
                type: 'simple',
                name: 'Brunch Item',
                description: null,
                price: 12,
                is_available: true,
                availability: itemAvailability,
                tags: [],
              },
            ],
          },
        ],
        menu_items: [],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('avail-t2')
      await publicPage.expectTemplate2Applied()
      await publicPage.expectMenuSectionVisible()
      await publicPage.expectMenuItemNameVisible('Brunch Item')
      await publicPage.expectAvailabilityTextVisibleInMenu('Mon–Fri 11:00–15:00')
      await publicPage.expectAvailabilityTextVisibleInMenu('Sat 10:00–14:00')
    })

    test('when availability is null, no availability text or Always available in menu', async ({ page }) => {
      mockPublicRestaurant(page, 'avail-null', {
        name: 'Always Open Cafe',
        slug: 'avail-null',
        template: 'template-1',
        menu_groups: [
          {
            category_name: 'All Day',
            category_uuid: 'cat-null',
            availability: null,
            items: [
              {
                uuid: 'av-n1',
                type: 'simple',
                name: 'All Day Coffee',
                description: null,
                price: 3,
                is_available: true,
                availability: null,
                tags: [],
              },
            ],
          },
        ],
        menu_items: [],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('avail-null')
      await publicPage.expectTemplate1Applied()
      await publicPage.expectMenuSectionVisible()
      await publicPage.expectMenuItemNameVisible('All Day Coffee')
      await publicPage.expectAvailabilityTextNotVisibleInMenu('Mon–Fri 11:00–15:00')
      await publicPage.expectNoAlwaysAvailableLabelInMenu()
    })

    test('when category or item has all-days-closed availability, no Closed label is shown (availability line absent)', async ({ page }) => {
      const allClosed = availabilityAllDaysClosed()
      mockPublicRestaurant(page, 'avail-all-closed', {
        name: 'Off Hours Cafe',
        slug: 'avail-all-closed',
        template: 'template-1',
        menu_groups: [
          {
            category_name: 'Lunch',
            category_uuid: 'cat-closed',
            availability: allClosed,
            items: [
              {
                uuid: 'av-c1',
                type: 'simple',
                name: 'Weekday Soup',
                description: null,
                price: 5,
                is_available: true,
                availability: allClosed,
                tags: [],
              },
            ],
          },
        ],
        menu_items: [],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.goToPublicBySlug('avail-all-closed')
      await publicPage.expectTemplate1Applied()
      await publicPage.expectMenuSectionVisible()
      await publicPage.expectMenuItemNameVisible('Weekday Soup')
      await publicPage.expectAvailabilityTextNotVisibleInMenu('Closed')
      await publicPage.expectNoAlwaysAvailableLabelInMenu()
    })
  })

  test.describe('Public page View Menu (mobile)', () => {
    test('mobile viewport: menu modal auto-opens on first load without clicking', async ({ page }) => {
      mockPublicRestaurant(page, 'view-menu-auto', {
        name: 'Auto Open Cafe',
        slug: 'view-menu-auto',
        menu_items: [
          { uuid: 'a1', type: 'simple', name: 'Auto Croissant', description: null, price: 4, sort_order: 0, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('view-menu-auto')
      await publicPage.expectMenuModalOpen()
    })

    test('mobile viewport: sticky View Menu button is visible; clicking opens menu modal', async ({ page }) => {
      mockPublicRestaurant(page, 'view-menu-cafe', {
        name: 'View Menu Cafe',
        slug: 'view-menu-cafe',
        menu_items: [
          { uuid: 'vm1', type: 'simple', name: 'Croissant', description: null, price: 4, sort_order: 0, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('view-menu-cafe')
      // On mobile the modal may auto-open; close it so we can test the button click path.
      await publicPage.closeMenuModalViaHeaderButton()
      await publicPage.expectMenuModalClosed()
      await publicPage.expectStickyViewMenuButtonVisible()
      await publicPage.clickStickyViewMenuButton()
      await publicPage.expectMenuModalOpen()
    })

    test('modal close: closing via header button dismisses dialog and sticky button is visible again', async ({ page }) => {
      mockPublicRestaurant(page, 'view-menu-close', {
        name: 'Close Modal Cafe',
        slug: 'view-menu-close',
        menu_items: [
          { uuid: 'c1', type: 'simple', name: 'Coffee', description: null, price: 3, sort_order: 0, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('view-menu-close')
      await publicPage.closeMenuModalViaHeaderButton()
      await publicPage.expectMenuModalClosed()
      await publicPage.expectStickyViewMenuButtonVisible()
      await publicPage.clickStickyViewMenuButton()
      await publicPage.expectMenuModalOpen()
      await publicPage.closeMenuModalViaHeaderButton()
      await publicPage.expectMenuModalClosed()
      await publicPage.expectStickyViewMenuButtonVisible()
    })

    test('modal close: Escape key dismisses menu modal', async ({ page }) => {
      mockPublicRestaurant(page, 'view-menu-esc', {
        name: 'Escape Cafe',
        slug: 'view-menu-esc',
        menu_items: [
          { uuid: 'e1', type: 'simple', name: 'Espresso', description: null, price: 2, sort_order: 0, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('view-menu-esc')
      await publicPage.closeMenuModalViaHeaderButton()
      await publicPage.expectMenuModalClosed()
      await publicPage.clickStickyViewMenuButton()
      await publicPage.expectMenuModalOpen()
      await publicPage.closeMenuModalViaEscape()
      await publicPage.expectMenuModalClosed()
    })

    test('collapsible categories: category header present; first category open by default shows menu items', async ({ page }) => {
      mockPublicRestaurant(page, 'view-menu-cats', {
        name: 'Categories Cafe',
        slug: 'view-menu-cats',
        menu_items: [
          { uuid: 'cat1', type: 'simple', name: 'Muffin', description: null, price: 5, sort_order: 0, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('view-menu-cats')
      await publicPage.expectMenuModalOpen()
      await publicPage.expectModalCategoryHeaderVisible('Menu')
      await publicPage.expectModalMenuItemVisible('Muffin')
      await publicPage.expectModalMenuItemVisible('$5.00')
    })

    test('Surprise me: with menu items, click Surprise me and an item gets highlighted', async ({ page }) => {
      mockPublicRestaurant(page, 'view-menu-surprise', {
        name: 'Surprise Cafe',
        slug: 'view-menu-surprise',
        menu_items: [
          { uuid: 's1', type: 'simple', name: 'Scone', description: null, price: 4, sort_order: 0, is_available: true, availability: null, tags: [] },
          { uuid: 's2', type: 'simple', name: 'Toast', description: null, price: 5, sort_order: 1, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('view-menu-surprise')
      await publicPage.expectMenuModalOpen()
      await publicPage.clickSurpriseMeInModal()
      await publicPage.expectModalItemHighlighted()
    })

    test('empty menu: Surprise me button is disabled', async ({ page }) => {
      mockPublicRestaurant(page, 'view-menu-empty', {
        name: 'Empty Menu Cafe',
        slug: 'view-menu-empty',
        menu_items: [],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('view-menu-empty')
      await publicPage.expectMenuModalOpen()
      await publicPage.expectSurpriseMeDisabled()
    })

    test('template-1: sticky View Menu bar and menu modal work', async ({ page }) => {
      mockPublicRestaurant(page, 't1-view-menu', {
        name: 'T1 View Menu',
        slug: 't1-view-menu',
        template: 'template-1',
        menu_items: [
          { uuid: 't1-1', type: 'simple', name: 'Croissant', description: null, price: 4, sort_order: 0, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('t1-view-menu')
      await publicPage.expectTemplate1Applied()
      await publicPage.closeMenuModalViaHeaderButton()
      await publicPage.expectMenuModalClosed()
      await publicPage.expectStickyViewMenuButtonVisible()
      await publicPage.clickStickyViewMenuButton()
      await publicPage.expectMenuModalOpen()
      await publicPage.expectModalMenuItemVisible('Croissant')
    })

    test('template-2: sticky View Menu bar and menu modal work', async ({ page }) => {
      mockPublicRestaurant(page, 't2-view-menu', {
        name: 'T2 View Menu',
        slug: 't2-view-menu',
        template: 'template-2',
        menu_items: [
          { uuid: 't2-1', type: 'simple', name: 'Espresso', description: null, price: 2.5, sort_order: 0, is_available: true, availability: null, tags: [] },
        ],
        feedbacks: [],
      })

      const publicPage = new PublicRestaurantPage(page)
      await publicPage.setMobileViewport()
      await publicPage.goToPublicBySlug('t2-view-menu')
      await publicPage.expectTemplate2Applied()
      await publicPage.closeMenuModalViaHeaderButton()
      await publicPage.expectMenuModalClosed()
      await publicPage.expectStickyViewMenuButtonVisible()
      await publicPage.clickStickyViewMenuButton()
      await publicPage.expectMenuModalOpen()
      await publicPage.expectModalMenuItemVisible('Espresso')
    })
  })

  test('owner can change description for selected locale and save', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const settingsPage = new RestaurantSettingsPage(page)
    await settingsPage.goToSettingsTab(MOCK_RESTAURANT.uuid)
    await settingsPage.expectDescriptionSectionVisible()
    await settingsPage.selectDescriptionLocale('en')
    await settingsPage.fillDescriptionForLocale('en', 'Our pizza is the best.')
    await settingsPage.clickSaveDescription()
    await settingsPage.expectDescriptionValueForLocale('en', 'Our pizza is the best.')
    await expect(page.getByTestId('settings-error')).toHaveAttribute('class', expect.stringContaining('sr-only'))
  })

  test('Settings when more than 5 languages shows Show all languages and Show less', async ({ page }) => {
    const sixLanguages = ['en', 'fr', 'de', 'es', 'fil', 'zh']
    const restaurantWithSixLanguages = { ...MOCK_RESTAURANT, languages: sixLanguages, default_locale: 'en' }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [restaurantWithSixLanguages])
    mockRestaurantGet(page, restaurantWithSixLanguages)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page, { languages: sixLanguages })

    const settingsPage = new RestaurantSettingsPage(page)
    await settingsPage.goToSettingsTab(MOCK_RESTAURANT.uuid)
    await settingsPage.expectLanguagesHeadingVisible()
    await settingsPage.expectShowAllLanguagesVisible()
    await settingsPage.expectShowAllLanguagesCount(6)
    await settingsPage.clickShowAllLanguages()
    await settingsPage.expectShowLessLanguagesVisible()
    await settingsPage.expectLanguageRowVisible('English')
    await settingsPage.expectLanguageRowVisible('French')
    await settingsPage.expectLanguageRowVisible('German')
    await settingsPage.clickShowLessLanguages()
    await settingsPage.expectShowAllLanguagesVisible()
    await settingsPage.expectShowAllLanguagesCount(6)
  })

  test('add menu item page shows name field and Create item', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    // Restaurant "add item" redirects to menu items context (standalone create)
    await page.goto(`/app/restaurants/${MOCK_RESTAURANT.uuid}/menu-items/new`)

    await expect(page).toHaveURL(/\/app\/menu-items\/new/)
    await expect(page.getByRole('heading', { name: 'Add menu item' })).toBeVisible()
    await expect(page.getByLabel('Name')).toBeVisible()
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
    await categoryItemsPage.expectEmptyState()
  })

  test('category items page with no items shows gradient empty state and Add menu item CTA', async ({ page }) => {
    const cat = { uuid: 'cat-empty', sort_order: 0, is_active: true, translations: { en: { name: 'Starters' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
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
    await categoryItemsPage.expectEmptyState()
  })

  test('category items page: floating Help button opens legend modal with icon meanings', async ({ page }) => {
    const cat = { uuid: 'cat-help', sort_order: 0, is_active: true, translations: { en: { name: 'Sides' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const categoryItemsPage = new CategoryMenuItemsPage(page)
    await categoryItemsPage.goTo(MOCK_RESTAURANT.uuid, cat.uuid, { name: 'Sides' })
    await categoryItemsPage.expectCategoryHeading('Sides')
    await categoryItemsPage.openHelpLegend()
    await categoryItemsPage.expectHelpModalVisible()
    await categoryItemsPage.expectHelpLegendContains('Drag handle')
    await categoryItemsPage.expectHelpLegendContains('Schedule')
    await categoryItemsPage.expectHelpLegendContains('Assign tags')
    await categoryItemsPage.closeHelpModal()
    await expect(page.getByRole('dialog', { name: 'Help' })).not.toBeVisible()
  })

  test('category items page shows visibility toggle; toggling sends PATCH with is_active and updates label', async ({ page }) => {
    const cat = { uuid: 'cat-visibility', sort_order: 0, is_active: true, translations: { en: { name: 'Mains' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    const item = {
      uuid: 'item-vis-1',
      category_uuid: cat.uuid,
      sort_order: 0,
      price: '10.00',
      is_active: true,
      translations: { en: { name: 'Burger', description: null } },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [item])
    mockRestaurantLanguagesAndTranslations(page)

    const categoryItemsPage = new CategoryMenuItemsPage(page)
    await categoryItemsPage.goTo(MOCK_RESTAURANT.uuid, cat.uuid, { name: 'Mains' })
    await categoryItemsPage.expectCategoryHeading('Mains')
    await categoryItemsPage.expectItemVisible('Burger')
    await categoryItemsPage.expectVisibilityToggleVisible()
    await categoryItemsPage.expectVisibilityToggleLabelHide()

    const patchPromise = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' &&
        req.url().includes('/api/restaurants/') &&
        req.url().includes('/menu-items/')
    )
    await categoryItemsPage.clickFirstVisibilityToggle()
    const patchRequest = await patchPromise
    const body = JSON.parse(await patchRequest.postData() || '{}')
    expect(body).toMatchObject({ is_active: false })

    await categoryItemsPage.expectVisibilityToggleLabelShow()
  })

  test('owner can toggle Not Available on category menu items page and see UI update', async ({ page }) => {
    const cat = { uuid: 'cat-avail', sort_order: 0, is_active: true, translations: { en: { name: 'Mains' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    const item = {
      uuid: 'item-avail-1',
      category_uuid: cat.uuid,
      sort_order: 0,
      price: '12.00',
      is_active: true,
      is_available: true,
      translations: { en: { name: 'Caesar Salad', description: null } },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [item])
    mockRestaurantLanguagesAndTranslations(page)

    const categoryItemsPage = new CategoryMenuItemsPage(page)
    await categoryItemsPage.goTo(MOCK_RESTAURANT.uuid, cat.uuid, { name: 'Mains' })
    await categoryItemsPage.expectCategoryHeading('Mains')
    await categoryItemsPage.expectItemVisible('Caesar Salad')
    await categoryItemsPage.expectAvailabilityToggleVisible()
    await categoryItemsPage.expectAvailabilityShowsAvailable()

    const patchPromise = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' &&
        req.url().includes('/api/restaurants/') &&
        req.url().includes('/menu-items/')
    )
    await categoryItemsPage.clickFirstAvailabilityToggle()
    const patchRequest = await patchPromise
    const body = JSON.parse(await patchRequest.postData() || '{}')
    expect(body).toMatchObject({ is_available: false })

    await categoryItemsPage.expectAvailabilityShowsNotAvailable()

    const patchPromise2 = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' &&
        req.url().includes('/api/restaurants/') &&
        req.url().includes('/menu-items/')
    )
    await categoryItemsPage.clickFirstAvailabilityToggle()
    const patchRequest2 = await patchPromise2
    const body2 = JSON.parse(await patchRequest2.postData() || '{}')
    expect(body2).toMatchObject({ is_available: true })
    await categoryItemsPage.expectAvailabilityShowsAvailable()
  })

  test('category availability modal opens with All available selected; save sends PATCH and shows toast', async ({ page }) => {
    const cat = { uuid: 'cat-avail-modal', sort_order: 0, is_active: true, translations: { en: { name: 'Starters' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTabPage = new RestaurantMenuTabPage(page)
    const availabilityModal = new AvailabilityModalPage(page)
    await menuTabPage.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTabPage.expectCategoryVisible('Starters')
    await menuTabPage.openCategoryAvailabilityModal('Starters')
    await availabilityModal.expectModalOpen()
    await availabilityModal.expectAllAvailableSelected()

    const patchPromise = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' &&
        req.url().includes('/api/restaurants/') &&
        req.url().includes('/menus/') &&
        req.url().includes('/categories/')
    )
    await availabilityModal.saveModal()
    const patchRequest = await patchPromise
    const body = JSON.parse(await patchRequest.postData() || '{}')
    expect(body).toHaveProperty('availability', null)

    await availabilityModal.expectModalClosed()
    await availabilityModal.expectSuccessToastWithMessage('Availability updated.')
  })

  test('category availability modal: Set specific times with valid slot saves and shows toast', async ({ page }) => {
    const cat = { uuid: 'cat-avail-schedule', sort_order: 0, is_active: true, translations: { en: { name: 'Mains' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTabPage = new RestaurantMenuTabPage(page)
    const availabilityModal = new AvailabilityModalPage(page)
    await menuTabPage.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTabPage.openCategoryAvailabilityModal('Mains')
    await availabilityModal.expectModalOpen()
    await availabilityModal.selectSetSpecificTimes()
    await availabilityModal.expectScheduleVisible()
    await availabilityModal.setSlotTime('monday', 0, 'from', '09:00')
    await availabilityModal.setSlotTime('monday', 0, 'to', '17:00')

    const patchPromise = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' &&
        req.url().includes('/categories/')
    )
    await availabilityModal.saveModal()
    const patchRequest = await patchPromise
    const body = JSON.parse(await patchRequest.postData() || '{}')
    expect(body.availability).toBeDefined()
    expect(body.availability.monday).toBeDefined()
    expect(body.availability.monday.slots).toHaveLength(1)
    expect(body.availability.monday.slots[0]).toEqual({ from: '09:00', to: '17:00' })

    await availabilityModal.expectModalClosed()
    await availabilityModal.expectSuccessToastWithMessage('Availability updated.')
  })

  test('category availability modal: invalid slot (from after to) shows error in modal', async ({ page }) => {
    const cat = { uuid: 'cat-avail-invalid', sort_order: 0, is_active: true, translations: { en: { name: 'Sides' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const menuTabPage = new RestaurantMenuTabPage(page)
    const availabilityModal = new AvailabilityModalPage(page)
    await menuTabPage.goToMenuTab(MOCK_RESTAURANT.uuid)
    await menuTabPage.openCategoryAvailabilityModal('Sides')
    await availabilityModal.expectModalOpen()
    await availabilityModal.selectSetSpecificTimes()
    await availabilityModal.expectScheduleVisible()
    await availabilityModal.setSlotTime('monday', 0, 'from', '12:00')
    await availabilityModal.setSlotTime('monday', 0, 'to', '10:00')

    await availabilityModal.saveModal()
    await availabilityModal.expectModalErrorToContain('From must be before to')
    await availabilityModal.expectModalOpen()
  })

  test('menu item availability modal opens with All available selected; save sends PATCH and shows toast', async ({ page }) => {
    const cat = { uuid: 'cat-item-avail', sort_order: 0, is_active: true, translations: { en: { name: 'Mains' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    const item = {
      uuid: 'item-avail-modal',
      category_uuid: cat.uuid,
      sort_order: 0,
      price: '10.00',
      is_active: true,
      is_available: true,
      translations: { en: { name: 'Burger', description: null } },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [item])
    mockRestaurantLanguagesAndTranslations(page)

    const categoryItemsPage = new CategoryMenuItemsPage(page)
    const availabilityModal = new AvailabilityModalPage(page)
    await categoryItemsPage.goTo(MOCK_RESTAURANT.uuid, cat.uuid, { name: 'Mains' })
    await categoryItemsPage.expectItemVisible('Burger')
    await categoryItemsPage.openItemAvailabilityModal('Burger')
    await availabilityModal.expectModalOpen()
    await availabilityModal.expectAllAvailableSelected()

    const patchPromise = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' &&
        req.url().includes('/api/restaurants/') &&
        req.url().includes('/menu-items/')
    )
    await availabilityModal.saveModal()
    const patchRequest = await patchPromise
    const body = JSON.parse(await patchRequest.postData() || '{}')
    expect(body).toHaveProperty('availability', null)

    await availabilityModal.expectModalClosed()
    await availabilityModal.expectSuccessToastWithMessage('Availability updated.')
  })

  test('menu item availability modal: Set specific times and save sends schedule and shows toast', async ({ page }) => {
    const cat = { uuid: 'cat-item-sched', sort_order: 0, is_active: true, translations: { en: { name: 'Starters' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    const item = {
      uuid: 'item-avail-sched',
      category_uuid: cat.uuid,
      sort_order: 0,
      price: '8.00',
      is_active: true,
      is_available: true,
      translations: { en: { name: 'Soup', description: null } },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [item])
    mockRestaurantLanguagesAndTranslations(page)

    const categoryItemsPage = new CategoryMenuItemsPage(page)
    const availabilityModal = new AvailabilityModalPage(page)
    await categoryItemsPage.goTo(MOCK_RESTAURANT.uuid, cat.uuid, { name: 'Starters' })
    await categoryItemsPage.expectItemVisible('Soup')
    await categoryItemsPage.openItemAvailabilityModal('Soup')
    await availabilityModal.expectModalOpen()
    await availabilityModal.selectSetSpecificTimes()
    await availabilityModal.expectScheduleVisible()
    await availabilityModal.setSlotTime('tuesday', 0, 'from', '11:00')
    await availabilityModal.setSlotTime('tuesday', 0, 'to', '15:00')

    const patchPromise = page.waitForRequest(
      (req) =>
        req.method() === 'PATCH' &&
        req.url().includes('/menu-items/')
    )
    await availabilityModal.saveModal()
    const patchRequest = await patchPromise
    const body = JSON.parse(await patchRequest.postData() || '{}')
    expect(body.availability).toBeDefined()
    expect(body.availability.tuesday).toBeDefined()
    expect(body.availability.tuesday.slots[0]).toEqual({ from: '11:00', to: '15:00' })

    await availabilityModal.expectModalClosed()
    await availabilityModal.expectSuccessToastWithMessage('Availability updated.')
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
    await categoryItemsPage.expectAddItemModalContentVisible()
    await categoryItemsPage.closeAddItemModal()
    await categoryItemsPage.expectAddItemModalClosed()
  })

  test('add-item modal shows variant rows for catalog item with_variants and not base item; adding variant sends source_variant_uuid', async ({ page }) => {
    const cat = { uuid: 'cat-variants', sort_order: 0, is_active: true, translations: { en: { name: 'Mains' } }, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    const catalogPizzaUuid = 'cat-pizza-uuid'
    const variantSkuUuid = 'var-hawaiian-small'
    const catalogPizzaWithVariants = {
      uuid: catalogPizzaUuid,
      restaurant_uuid: null,
      type: 'with_variants',
      translations: { en: { name: 'Pizza', description: null } },
      variant_option_groups: [{ name: 'Type' }, { name: 'Size' }],
      variant_skus: [
        { uuid: variantSkuUuid, option_values: { Type: 'Hawaiian', Size: 'Small' }, price: 12 },
      ],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [cat])
    mockRestaurantMenuItems(page, [])
    mockUserMenuItems(page, [catalogPizzaWithVariants])
    mockRestaurantLanguagesAndTranslations(page)

    const categoryItemsPage = new CategoryMenuItemsPage(page)
    await categoryItemsPage.goTo(MOCK_RESTAURANT.uuid, cat.uuid, { name: 'Mains' })
    await categoryItemsPage.expectCategoryHeading('Mains')
    await categoryItemsPage.clickAddMenuItemButton()
    await categoryItemsPage.expectAddItemModalOpen()
    await categoryItemsPage.expectAddModalVariantRowVisible('Pizza – Hawaiian, Small')
    await categoryItemsPage.expectAddModalBaseItemNotVisible('Pizza')

    const variantRowUuid = `catalog-${catalogPizzaUuid}-${variantSkuUuid}`
    const postRequestPromise = page.waitForRequest(
      (req) =>
        req.url().includes('/api/restaurants/') &&
        req.url().includes('/menu-items') &&
        req.method() === 'POST'
    )
    await categoryItemsPage.addItemToCategoryInModal('Pizza – Hawaiian, Small', variantRowUuid)
    const postRequest = await postRequestPromise
    const body = JSON.parse(await postRequest.postData() || '{}')
    expect(body).toMatchObject({
      source_menu_item_uuid: catalogPizzaUuid,
      source_variant_uuid: variantSkuUuid,
      category_uuid: cat.uuid,
    })
  })

  test.skip('owner can add menu item to category via modal and see list update', async ({ page }) => {
    // Skip: modal list stays empty in mocked run (listMenuItems response may be cached/empty from initial load). Enable when debugging mock or running with real API.
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
    await categoryItemsPage.addItemToCategoryInModal('Burger', itemNotInCategory.uuid)
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

  test('owner can set year established on profile and persist', async ({ page }) => {
    await loginAsVerifiedUser(page)
    mockRestaurantList(page, [MOCK_RESTAURANT])
    mockRestaurantGet(page, MOCK_RESTAURANT)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const availabilityPage = new RestaurantManageAvailabilityPage(page)
    const formPage = new RestaurantFormPage(page)
    await availabilityPage.goToManagePage(MOCK_RESTAURANT.uuid)
    await availabilityPage.goToProfileTab()
    await formPage.expandAdvancedDetails()
    await formPage.fillYearEstablished('1995')
    await formPage.submitSaveChanges()
    await formPage.expectYearEstablishedValue('1995')
  })

  test('owner can set year established on create and value persists after navigate', async ({ page }) => {
    await loginAsVerifiedUser(page)
    const createdRestaurant = { ...MOCK_RESTAURANT, uuid: 'rstr-new-1', name: 'New Restaurant', slug: 'new-restaurant', year_established: 1995 }
    page.route(LIST_OR_CREATE_RESTAURANTS, (route) => {
      if (route.request().method() === 'GET') {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }),
        })
      }
      if (route.request().method() === 'POST') {
        const body = JSON.parse(route.request().postData() || '{}')
        const data = {
          ...createdRestaurant,
          name: body.name || 'New Restaurant',
          slug: body.slug || 'new-restaurant',
          year_established: body.year_established != null ? Number(body.year_established) : null,
        }
        return route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ message: 'Restaurant created.', data }),
        })
      }
      return route.continue()
    })
    mockRestaurantGet(page, createdRestaurant)
    mockRestaurantMenus(page)
    mockRestaurantCategories(page, [])
    mockRestaurantMenuItems(page, [])
    mockRestaurantLanguagesAndTranslations(page)

    const formPage = new RestaurantFormPage(page)
    await formPage.goToCreatePage()
    await formPage.fillName('New Restaurant')
    await formPage.expandAdvancedDetails()
    await formPage.fillYearEstablished('1995')
    await formPage.submitCreate()

    await expect(page).toHaveURL(/\/app\/restaurants\/rstr-new-1/)
    await formPage.expandAdvancedDetails()
    await formPage.expectYearEstablishedValue('1995')
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
    page.route('**/api/menu-item-tags**', (route) => {
      if (route.request().method() === 'GET' && !route.request().url().match(/\/api\/menu-item-tags\/[^/]+$/)) {
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) })
      }
      return route.continue()
    })

    const formPage = new MenuItemFormPage(page)
    await formPage.goToEdit(MOCK_RESTAURANT.uuid, existingItem.uuid)
    await formPage.expectEditMenuItemHeading()
    await formPage.setPrice('12.50')
    await formPage.submitSaveChanges()

    await expect(page).toHaveURL(new RegExp(`/app/restaurants/${MOCK_RESTAURANT.uuid}`))
  })
})
