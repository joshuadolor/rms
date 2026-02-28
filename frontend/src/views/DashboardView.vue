<template>
  <div>
    <!-- Superadmin dashboard: stats only -->
    <template v-if="user?.isSuperadmin">
      <div data-testid="superadmin-dashboard">
      <header class="mb-6 lg:mb-8">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">{{ $t('app.dashboard') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $t('app.dashboardSuperadminSubtitle') }}</p>
      </header>
      <div v-if="statsError" class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm" role="alert">
        {{ statsError }}
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
        <div class="rms-dashboard-card bg-white dark:bg-zinc-900 p-5 lg:p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200 min-h-[44px] flex items-center justify-between gap-4">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">{{ $t('app.restaurants') }}</p>
            <p class="text-3xl lg:text-4xl font-bold tracking-tight text-charcoal dark:text-white">{{ stats?.restaurantsCount ?? '—' }}</p>
          </div>
          <div class="w-14 h-14 rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0" aria-hidden="true">
            <span class="material-icons text-2xl text-amber-600 dark:text-amber-400">storefront</span>
          </div>
        </div>
        <div class="rms-dashboard-card bg-white dark:bg-zinc-900 p-5 lg:p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200 min-h-[44px] flex items-center justify-between gap-4">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">{{ $t('app.users') }}</p>
            <p class="text-3xl lg:text-4xl font-bold tracking-tight text-charcoal dark:text-white">{{ stats?.usersCount ?? '—' }}</p>
          </div>
          <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center shrink-0" aria-hidden="true">
            <span class="material-icons text-2xl text-slate-600 dark:text-slate-300">people</span>
          </div>
        </div>
        <div class="rms-dashboard-card bg-white dark:bg-zinc-900 p-5 lg:p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200 min-h-[44px] flex items-center justify-between gap-4">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">{{ $t('app.paidUsers') }}</p>
            <p class="text-3xl lg:text-4xl font-bold tracking-tight text-charcoal dark:text-white">{{ stats?.paidUsersCount ?? '—' }}</p>
          </div>
          <div class="w-14 h-14 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0" aria-hidden="true">
            <span class="material-icons text-2xl text-emerald-600 dark:text-emerald-400">payments</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="p-4 lg:p-6">
          <h3 class="font-bold text-charcoal dark:text-white mb-2">{{ $t('app.dashboardWelcome') }} {{ user?.fullName }}</h3>
          <p class="text-slate-500 dark:text-slate-400 text-sm">{{ $t('app.dashboardSuperadminHint') }}</p>
        </div>
      </div>
      </div>
    </template>

    <!-- Owner dashboard -->
    <template v-else>
      <header class="mb-6 lg:mb-8">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">{{ $t('app.dashboard') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $t('app.dashboardSubtitle') }}</p>
      </header>

    <!-- Stats Grid: Restaurants, Menu items, Feedbacks (with approved/rejected inside) -->
    <div v-if="ownerStatsError" class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm" role="alert">
      {{ ownerStatsError }}
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
      <!-- Restaurants -->
      <div class="rms-dashboard-card bg-white dark:bg-zinc-900 p-5 lg:p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200 min-h-[44px] flex items-center justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">{{ $t('app.restaurants') }}</p>
          <p class="text-3xl lg:text-4xl font-bold tracking-tight text-charcoal dark:text-white">{{ ownerStats?.restaurantsCount ?? '—' }}</p>
        </div>
        <div class="w-14 h-14 rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0" aria-hidden="true">
          <span class="material-icons text-2xl text-amber-600 dark:text-amber-400">storefront</span>
        </div>
      </div>
      <!-- Menu items -->
      <div class="rms-dashboard-card bg-white dark:bg-zinc-900 p-5 lg:p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200 min-h-[44px] flex items-center justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">{{ $t('app.menuItems') }}</p>
          <p class="text-3xl lg:text-4xl font-bold tracking-tight text-charcoal dark:text-white">{{ ownerStats?.menuItemsCount ?? '—' }}</p>
        </div>
        <div class="w-14 h-14 rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0" aria-hidden="true">
          <span class="material-icons text-2xl text-amber-600 dark:text-amber-400">menu_book</span>
        </div>
      </div>
      <!-- Feedbacks: total + approved/rejected inside -->
      <div class="rms-dashboard-card bg-white dark:bg-zinc-900 p-5 lg:p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-200 min-h-[44px] flex flex-col sm:col-span-2">
        <div class="flex items-center justify-between gap-4 mb-3">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">{{ $t('app.feedbacks') }}</p>
            <p class="text-3xl lg:text-4xl font-bold tracking-tight text-charcoal dark:text-white">{{ ownerStats?.feedbacksTotal ?? '—' }}</p>
          </div>
          <div class="w-14 h-14 rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0" aria-hidden="true">
            <span class="material-icons text-2xl text-amber-600 dark:text-amber-400">rate_review</span>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100 dark:border-slate-700/80">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-xs font-medium px-2.5 py-1 min-h-[28px]">
            <span class="w-1.5 h-1.5 rounded-full bg-green-500 shrink-0" aria-hidden="true" />
            {{ ownerStats?.feedbacksApproved ?? 0 }} {{ $t('app.feedbacksApproved') }}
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-medium px-2.5 py-1 min-h-[28px]">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0" aria-hidden="true" />
            {{ ownerStats?.feedbacksRejected ?? 0 }} {{ $t('app.feedbacksRejected') }}
          </span>
        </div>
      </div>
    </div>

    <!-- Welcome card -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div class="p-4 lg:p-6">
        <h3 class="font-bold text-charcoal dark:text-white mb-2">{{ $t('app.dashboardWelcome') }} {{ user?.fullName }}</h3>
        <p class="text-slate-500 dark:text-slate-400 text-sm">{{ $t('app.dashboardSignedIn', { email: user?.email }) }}</p>
      </div>
    </div>
    </template>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/stores/app'
import { superadminService, dashboardService, normalizeApiError } from '@/services'

const appStore = useAppStore()
const { user } = storeToRefs(appStore)

const stats = ref(null)
const statsError = ref('')
const ownerStats = ref(null)
const ownerStatsError = ref('')

async function loadSuperadminStats() {
  if (!user.value?.isSuperadmin) return
  statsError.value = ''
  try {
    stats.value = await superadminService.getStats()
  } catch (err) {
    const normalized = normalizeApiError(err)
    statsError.value = normalized.message
  }
}

async function loadOwnerStats() {
  if (user.value?.isSuperadmin) return
  ownerStatsError.value = ''
  try {
    ownerStats.value = await dashboardService.getStats()
  } catch (err) {
    const normalized = normalizeApiError(err)
    ownerStatsError.value = normalized.message
  }
}

onMounted(() => {
  loadSuperadminStats()
  loadOwnerStats()
})
watch(user, () => {
  loadSuperadminStats()
  loadOwnerStats()
}, { immediate: false })
</script>
