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
  // Root: on subdomain (e.g. test.rms.local) show restaurant page; else landing. URL stays /
  {
    path: '/',
    name: 'Landing',
    component: () => import('@/views/RootView.vue'),
    meta: { guest: true },
  },
  // Public restaurant by path (e.g. rms.local/r/pizza when not using subdomain)
  {
    path: '/r/:slug',
    name: 'PublicRestaurant',
    component: () => import('@/views/PublicRestaurantView.vue'),
    meta: { requiresAuth: false },
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
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, _from, next) => {
  const appStore = useAppStore()
  const isAuthenticated = !!appStore.user
  const isVerified = appStore.user?.isEmailVerified ?? false

  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
    return
  }

  if (to.meta.guest && isAuthenticated) {
    next(isVerified ? { name: 'App' } : { name: 'VerifyEmail' })
    return
  }

  if (to.meta.requiresVerified && isAuthenticated && !isVerified) {
    next({ name: 'VerifyEmail' })
    return
  }

  if (to.name === 'VerifyEmail' && isAuthenticated && isVerified) {
    next({ name: 'App' })
    return
  }

  next()
})

export default router
