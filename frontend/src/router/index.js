import { createRouter, createWebHistory } from 'vue-router'
import { useAppStore } from '@/stores/app'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/login/callback',
    name: 'LoginCallback',
    component: () => import('@/views/LoginCallbackView.vue'),
    meta: { guest: true },
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('@/views/ForgotPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/reset-password',
    name: 'ResetPassword',
    component: () => import('@/views/ResetPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/views/RegisterView.vue'),
    meta: { guest: true },
  },
  {
    path: '/verify-email',
    name: 'VerifyEmail',
    component: () => import('@/views/VerifyEmailView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/email/verify',
    name: 'EmailVerifyConfirm',
    component: () => import('@/views/EmailVerifyConfirmView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/email/verify-new',
    name: 'EmailVerifyNew',
    component: () => import('@/views/EmailVerifyNewView.vue'),
    meta: { requiresAuth: false },
  },
  // Root: on subdomain (e.g. test.rms.local) show restaurant page; else landing. Public URLs are subdomain-only.
  {
    path: '/',
    name: 'Landing',
    component: () => import('@/views/RootView.vue'),
    meta: { guest: true },
  },
  // Public restaurant by slug (path-based; when SPA is loaded at /r/:slug, e.g. Vite dev without proxy).
  {
    path: '/r/:slug',
    name: 'PublicRestaurant',
    component: () => import('@/views/PublicRestaurantView.vue'),
    props: true,
    meta: { guest: true },
  },
  {
    path: '/app',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true, requiresVerified: true },
    children: [
      {
        path: '',
        name: 'App',
        component: () => import('@/views/DashboardView.vue'),
      },
      {
        path: 'restaurants',
        name: 'Restaurants',
        component: () => import('@/views/restaurants/RestaurantListView.vue'),
      },
      {
        path: 'menu-items',
        name: 'MenuItems',
        component: () => import('@/views/menu-items/MenuItemsView.vue'),
      },
      {
        path: 'menu-items/new',
        name: 'MenuItemNew',
        component: () => import('@/views/menu-items/StandaloneMenuItemCreateView.vue'),
      },
      {
        path: 'menu-items/:itemUuid/edit',
        name: 'MenuItemEdit',
        component: () => import('@/views/restaurants/MenuItemFormView.vue'),
        meta: { mode: 'edit', menuItemsModule: true },
      },
      {
        path: 'menu-item-tags',
        name: 'MenuItemTags',
        component: () => import('@/views/MenuItemTagsView.vue'),
      },
      {
        path: 'feedbacks',
        name: 'Feedbacks',
        component: () => import('@/views/feedbacks/FeedbacksLandingView.vue'),
      },
      {
        path: 'owner-feedback',
        name: 'OwnerFeedback',
        component: () => import('@/views/OwnerFeedbackView.vue'),
      },
      {
        path: 'feedbacks/restaurants/:restaurantUuid',
        name: 'FeedbacksList',
        component: () => import('@/views/feedbacks/FeedbacksListView.vue'),
      },
      {
        path: 'restaurants/new',
        name: 'RestaurantNew',
        component: () => import('@/views/restaurants/RestaurantFormView.vue'),
        meta: { mode: 'create' },
      },
      {
        path: 'restaurants/:uuid',
        name: 'RestaurantDetail',
        component: () => import('@/views/restaurants/RestaurantManageView.vue'),
      },
      {
        path: 'restaurants/:uuid/edit',
        name: 'RestaurantEdit',
        redirect: (to) => ({ name: 'RestaurantDetail', params: to.params, query: { tab: 'profile' } }),
      },
      {
        path: 'restaurants/:uuid/content',
        name: 'RestaurantContent',
        redirect: (to) => ({ name: 'RestaurantDetail', params: to.params, query: { tab: 'settings' } }),
      },
      {
        path: 'restaurants/:uuid/contacts',
        name: 'RestaurantContacts',
        redirect: (to) => ({ name: 'RestaurantDetail', params: to.params, query: { tab: 'profile' } }),
      },
      {
        path: 'restaurants/:uuid/menu-items',
        name: 'RestaurantMenuItems',
        redirect: (to) => ({ name: 'RestaurantDetail', params: to.params, query: { tab: 'menu' } }),
      },
      {
        path: 'restaurants/:uuid/categories/:categoryUuid/items',
        name: 'CategoryMenuItems',
        component: () => import('@/views/restaurants/CategoryMenuItemsView.vue'),
      },
      {
        path: 'restaurants/:uuid/menu-items/new',
        name: 'RestaurantMenuItemNew',
        redirect: () => ({ name: 'MenuItemNew' }),
      },
      {
        path: 'restaurants/:uuid/menu-items/:itemUuid/edit',
        name: 'RestaurantMenuItemEdit',
        component: () => import('@/views/restaurants/MenuItemFormView.vue'),
        meta: { mode: 'edit' },
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('@/views/ProfileView.vue'),
      },
      // Superadmin-only routes (guard checks is_superadmin)
      {
        path: 'superadmin/users',
        name: 'SuperadminUsers',
        component: () => import('@/views/superadmin/SuperadminUsersView.vue'),
        meta: { requiresSuperadmin: true },
      },
      {
        path: 'superadmin/owner-feedbacks',
        name: 'SuperadminOwnerFeedbacks',
        component: () => import('@/views/superadmin/SuperadminOwnerFeedbacksView.vue'),
        meta: { requiresSuperadmin: true },
      },
      {
        path: 'superadmin/restaurants',
        name: 'SuperadminRestaurants',
        component: () => import('@/views/superadmin/SuperadminRestaurantsView.vue'),
        meta: { requiresSuperadmin: true },
      },
      {
        path: 'superadmin/legal',
        name: 'SuperadminLegal',
        component: () => import('@/views/superadmin/SuperadminLegalView.vue'),
        meta: { requiresSuperadmin: true },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const appStore = useAppStore()
  const requiresAuth = to.matched.some((r) => r.meta.requiresAuth)
  const requiresVerified = to.matched.some((r) => r.meta.requiresVerified)
  const requiresSuperadmin = to.matched.some((r) => r.meta.requiresSuperadmin)
  const isGuestRoute = to.matched.some((r) => r.meta.guest)
  const needsBlockingAuthDecision = requiresAuth || requiresVerified || requiresSuperadmin
  if (needsBlockingAuthDecision) {
    await appStore.bootstrapAuth()
  } else {
    // Guest/public routes should render immediately; bootstrap runs in background.
    appStore.bootstrapAuth().catch(() => {})
  }
  const isAuthenticated = !!appStore.user
  const isVerified = appStore.user?.isEmailVerified ?? false
  const knownEmail = appStore.user?.email ? String(appStore.user.email) : ''

  if (requiresAuth && !isAuthenticated) {
    return {
      name: 'Login',
      query: {
        redirect: to.fullPath,
        ...(appStore.authBootstrapHadNetworkError ? { message: 'You appear offline. Please reconnect to continue.' } : {}),
      },
    }
  }

  const isPublicRestaurantRoute = to.name === 'PublicRestaurant'
  if (isGuestRoute && isAuthenticated && !isPublicRestaurantRoute) {
    return isVerified ? { name: 'App' } : (knownEmail ? { name: 'VerifyEmail', query: { email: knownEmail } } : { name: 'VerifyEmail' })
  }

  if (requiresVerified && isAuthenticated && !isVerified) {
    return knownEmail ? { name: 'VerifyEmail', query: { email: knownEmail } } : { name: 'VerifyEmail' }
  }

  if (requiresSuperadmin && isAuthenticated && !appStore.user?.isSuperadmin) {
    return { name: 'App' }
  }

  if (to.name === 'VerifyEmail' && isAuthenticated && isVerified) {
    return { name: 'App' }
  }
})

export default router
