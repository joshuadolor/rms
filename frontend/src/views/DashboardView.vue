<template>
  <div>
    <!-- Superadmin dashboard: stats only -->
    <template v-if="user?.isSuperadmin">
      <div data-testid="superadmin-dashboard">
      <header class="mb-6 lg:mb-8">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Dashboard</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">System overview.</p>
      </header>
      <div v-if="statsError" class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm" role="alert">
        {{ statsError }}
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
        <div class="bg-white dark:bg-zinc-900 p-4 lg:p-6 rounded-xl border border-slate-200 dark:border-slate-800 min-h-[44px] flex flex-col justify-center">
          <div class="flex items-center gap-3 mb-2">
            <div class="p-2 bg-primary/10 rounded-lg shrink-0">
              <span class="material-icons text-primary">storefront</span>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Restaurants</p>
          </div>
          <p class="text-2xl font-bold text-charcoal dark:text-white">{{ stats?.restaurantsCount ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-zinc-900 p-4 lg:p-6 rounded-xl border border-slate-200 dark:border-slate-800 min-h-[44px] flex flex-col justify-center">
          <div class="flex items-center gap-3 mb-2">
            <div class="p-2 bg-primary/10 rounded-lg shrink-0">
              <span class="material-icons text-primary">people</span>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Users</p>
          </div>
          <p class="text-2xl font-bold text-charcoal dark:text-white">{{ stats?.usersCount ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-zinc-900 p-4 lg:p-6 rounded-xl border border-slate-200 dark:border-slate-800 min-h-[44px] flex flex-col justify-center">
          <div class="flex items-center gap-3 mb-2">
            <div class="p-2 bg-primary/10 rounded-lg shrink-0">
              <span class="material-icons text-primary">payments</span>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Paid users</p>
          </div>
          <p class="text-2xl font-bold text-charcoal dark:text-white">{{ stats?.paidUsersCount ?? '—' }}</p>
        </div>
      </div>
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="p-4 lg:p-6">
          <h3 class="font-bold text-charcoal dark:text-white mb-2">Welcome, {{ user?.fullName }}</h3>
          <p class="text-slate-500 dark:text-slate-400 text-sm">Use <router-link to="/app/superadmin/users" class="text-primary hover:underline">Users</router-link> or <router-link to="/app/superadmin/restaurants" class="text-primary hover:underline">Restaurants</router-link> to manage the system.</p>
        </div>
      </div>
      </div>
    </template>

    <!-- Owner dashboard -->
    <template v-else>
      <header class="mb-6 lg:mb-8">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Dashboard</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage and monitor your restaurant in one place.</p>
      </header>

    <!-- Stats Grid (placeholder): 1 col mobile, 2 at md, 4 at lg -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-6 lg:mb-8">
      <div class="bg-white dark:bg-zinc-900 p-4 lg:p-6 rounded-xl border border-slate-200 dark:border-slate-800">
        <div class="flex justify-between items-start mb-3 lg:mb-4">
          <div class="p-2 bg-primary/10 rounded-lg">
            <span class="material-icons text-primary">shopping_bag</span>
          </div>
        </div>
        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Total Orders</p>
        <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">—</h3>
      </div>
      <div class="bg-white dark:bg-zinc-900 p-4 lg:p-6 rounded-xl border border-slate-200 dark:border-slate-800">
        <div class="flex justify-between items-start mb-3 lg:mb-4">
          <div class="p-2 bg-primary/10 rounded-lg">
            <span class="material-icons text-primary">payments</span>
          </div>
        </div>
        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Revenue</p>
        <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">—</h3>
      </div>
      <div class="bg-white dark:bg-zinc-900 p-4 lg:p-6 rounded-xl border border-slate-200 dark:border-slate-800">
        <div class="flex justify-between items-start mb-3 lg:mb-4">
          <div class="p-2 bg-primary/10 rounded-lg">
            <span class="material-icons text-primary">menu_book</span>
          </div>
        </div>
        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Menu Items</p>
        <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">—</h3>
      </div>
      <!-- Locations stat: hidden until maps integration; set showLocationsSection to true to re-enable -->
      <div v-if="showLocationsSection" class="bg-white dark:bg-zinc-900 p-4 lg:p-6 rounded-xl border border-slate-200 dark:border-slate-800">
        <div class="flex justify-between items-start mb-3 lg:mb-4">
          <div class="p-2 bg-primary/10 rounded-lg">
            <span class="material-icons text-primary">storefront</span>
          </div>
        </div>
        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Locations</p>
        <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">—</h3>
      </div>
    </div>

    <!-- Welcome card -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div class="p-4 lg:p-6">
        <h3 class="font-bold text-charcoal dark:text-white mb-2">Welcome, {{ user?.fullName }}</h3>
        <p class="text-slate-500 dark:text-slate-400 text-sm">Signed in as {{ user?.email }}. Use Profile &amp; Settings to update your account or password.</p>
      </div>
    </div>
    </template>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/stores/app'
import { superadminService } from '@/services'
import { normalizeApiError } from '@/services'

const appStore = useAppStore()
const { user } = storeToRefs(appStore)

const stats = ref(null)
const statsError = ref('')

async function loadStats() {
  if (!user.value?.isSuperadmin) return
  statsError.value = ''
  try {
    stats.value = await superadminService.getStats()
  } catch (err) {
    const normalized = normalizeApiError(err)
    statsError.value = normalized.message
  }
}

onMounted(() => loadStats())
watch(user, () => loadStats(), { immediate: false })

// Re-enable when maps integration is ready
const showLocationsSection = false
</script>
