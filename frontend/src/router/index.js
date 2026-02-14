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
  {
    path: '/',
    name: 'Landing',
    component: () => import('@/views/LandingView.vue'),
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
