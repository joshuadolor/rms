<template>
  <div class="min-h-screen bg-background-light dark:bg-background-dark flex font-display">
    <!-- Mobile: overlay when sidebar open -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-black/50 z-40 lg:hidden"
      aria-hidden="true"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar: drawer on mobile (max-lg), always visible on desktop (lg+) -->
    <aside
      class="w-64 bg-white dark:bg-zinc-900 border-r border-slate-200 dark:border-slate-800 flex flex-col fixed h-full z-50 max-lg:transition-transform max-lg:duration-200 max-lg:ease-out"
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
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-colors text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 min-h-[44px]"
          exact-active-class="!bg-primary/10 !text-primary"
          @click="closeSidebar"
        >
          <span class="material-icons">storefront</span>
          <span>Dashboard</span>
        </router-link>
        <router-link
          to="/app/profile"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-colors text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 min-h-[44px]"
          active-class="!bg-primary/10 !text-primary"
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
      <!-- Mobile top bar -->
      <header class="flex items-center justify-between gap-3 px-4 py-3 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-slate-800 lg:hidden shrink-0">
        <router-link to="/app" class="flex items-center gap-2">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shrink-0">
            <span class="material-icons text-white text-lg">restaurant</span>
          </div>
          <span class="font-bold text-lg tracking-tight text-charcoal dark:text-white">RMS</span>
        </router-link>
        <button
          type="button"
          class="min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"
          aria-label="Open menu"
          aria-expanded="sidebarOpen"
          @click="sidebarOpen = true"
        >
          <span class="material-icons text-2xl">menu</span>
        </button>
      </header>
      <main class="flex-1 p-4 lg:p-8">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/stores/app'

const router = useRouter()
const appStore = useAppStore()
const { user } = storeToRefs(appStore)

const sidebarOpen = ref(false)

const userInitial = computed(() => {
  const n = user.value?.fullName || user.value?.email || '?'
  return n.charAt(0).toUpperCase()
})

function closeSidebar() {
  sidebarOpen.value = false
}

function handleLogout() {
  appStore.logout()
  router.push({ name: 'Landing' })
}
</script>
