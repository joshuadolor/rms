<template>
  <div class="min-h-screen bg-background-light dark:bg-background-dark flex font-display">
    <!-- Mobile: overlay when sidebar open -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-black/50 z-40 lg:hidden"
      aria-hidden="true"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar: drawer on mobile (max-lg), always visible on desktop (lg+). inset-y-0 + min-h so it fills viewport height on mobile. -->
    <aside
      class="w-64 bg-white dark:bg-zinc-900 border-r border-slate-200 dark:border-slate-800 flex flex-col fixed inset-y-0 left-0 z-50 min-h-[100dvh] max-lg:transition-transform max-lg:duration-200 max-lg:ease-out"
      :class="sidebarOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-full'"
      aria-label="Main navigation"
    >
      <div class="p-6">
        <router-link to="/app" class="flex items-center gap-3" @click="closeSidebar">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-icons text-white text-xl">restaurant</span>
          </div>
          <h1 class="font-bold text-xl tracking-tight text-charcoal dark:text-white">RMS</h1>
        </router-link>
      </div>
      <nav class="flex-1 px-4 space-y-1">
        <router-link
          to="/app"
          class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-colors text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 min-h-[44px]"
          :class="{ 'nav-link-active': isNavActive('/app', true) }"
          @click="closeSidebar"
        >
          <span class="material-icons">dashboard</span>
          <span>Dashboard</span>
        </router-link>
        <router-link
          to="/app/restaurants"
          class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-colors text-slate-600 dark:text-slate-400 hover:bg-sage/10 dark:hover:bg-sage/15 hover:text-sage min-h-[44px]"
          :class="{ 'nav-link-active': isNavActive('/app/restaurants') }"
          @click="closeSidebar"
        >
          <span class="material-icons">storefront</span>
          <span>Restaurants</span>
        </router-link>
        <router-link
          to="/app/menu-items"
          class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-colors text-slate-600 dark:text-slate-400 hover:bg-sage/10 dark:hover:bg-sage/15 hover:text-sage min-h-[44px]"
          :class="{ 'nav-link-active': isNavActive('/app/menu-items') }"
          @click="closeSidebar"
        >
          <span class="material-icons">restaurant_menu</span>
          <span>Menu items</span>
        </router-link>
        <router-link
          to="/app/profile"
          class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-colors text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 min-h-[44px]"
          :class="{ 'nav-link-active': isNavActive('/app/profile') }"
          @click="closeSidebar"
        >
          <span class="material-icons">settings</span>
          <span>Profile &amp; Settings</span>
        </router-link>
      </nav>
      <div class="p-4 border-t border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-3 p-2">
          <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-semibold shrink-0">
            {{ userInitial }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-charcoal dark:text-white truncate">{{ user?.fullName }}</p>
            <p class="text-xs text-slate-500 truncate">{{ user?.email }}</p>
          </div>
          <button
            type="button"
            class="text-slate-400 hover:text-primary p-2 rounded min-h-[44px] min-w-[44px] flex items-center justify-center -m-1"
            title="Sign out"
            aria-label="Sign out"
            @click="handleLogout"
          >
            <span class="material-icons text-xl">logout</span>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main: full width on mobile, margin when lg -->
    <div class="flex-1 min-w-0 flex flex-col lg:ml-64">
      <!-- Mobile top bar: fixed on mobile so it stays visible when scrolling -->
      <header class="flex items-center justify-between gap-3 px-4 py-3 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-slate-800 lg:hidden shrink-0 fixed top-0 left-0 right-0 z-30 lg:static">
        <router-link to="/app" class="flex items-center gap-2">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shrink-0">
            <span class="material-icons text-white text-lg">restaurant</span>
          </div>
          <span class="font-bold text-lg tracking-tight text-charcoal dark:text-white">RMS</span>
        </router-link>
        <button
          type="button"
          class="min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"
          title="Open menu"
          aria-label="Open menu"
          aria-expanded="sidebarOpen"
          @click="sidebarOpen = true"
        >
          <span class="material-icons text-2xl">menu</span>
        </button>
      </header>
      <main class="flex-1 p-4 pt-14 lg:p-8 lg:pt-8">
        <!-- Sticky breadcrumbs: below mobile header when sticky (top-14), at top on desktop; only on routes that have breadcrumbs -->
        <div
          v-if="route.name && BREADCRUMB_CONFIG[route.name]"
          class="sticky z-10 -mx-4 -mt-14 px-4 pt-4 lg:-mx-8 lg:-mt-8 lg:px-8 lg:pt-8 max-lg:top-14 lg:top-0 py-3 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-slate-800 mb-4 lg:mb-6"
        >
          <AppBreadcrumbs />
        </div>
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/stores/app'
import { BREADCRUMB_CONFIG } from '@/config/breadcrumbs'
import AppBreadcrumbs from '@/components/AppBreadcrumbs.vue'

const router = useRouter()
const route = useRoute()
const appStore = useAppStore()
const { user } = storeToRefs(appStore)

const sidebarOpen = ref(false)

const BODY_CLASS = 'rms-app-layout'
onMounted(() => document.body.classList.add(BODY_CLASS))
onBeforeUnmount(() => document.body.classList.remove(BODY_CLASS))

const userInitial = computed(() => {
  const n = user.value?.fullName || user.value?.email || '?'
  return n.charAt(0).toUpperCase()
})

/** True when the current route should show this nav item as active. exact: only when path equals basePath; otherwise path equals basePath or starts with basePath + '/' */
function isNavActive(basePath, exact = false) {
  const path = route.path
  if (exact) return path === basePath || path === basePath + '/'
  return path === basePath || path === basePath + '/' || path.startsWith(basePath + '/')
}

function closeSidebar() {
  sidebarOpen.value = false
}

function handleLogout() {
  appStore.logout()
  router.push({ name: 'Landing' })
}
</script>

<style scoped>
/* Active nav: background + text color only. Do not use border-l on active states. */
.nav-link-active {
  @apply bg-primary/10 text-primary;
}
</style>
