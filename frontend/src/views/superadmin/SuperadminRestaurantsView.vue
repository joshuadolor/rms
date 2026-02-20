<template>
  <div data-testid="superadmin-restaurants-page">
    <header class="mb-6 lg:mb-8">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Restaurants</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">View-only list of all restaurants in the system.</p>
    </header>

    <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm" role="alert">
      {{ error }}
    </div>

    <div v-if="loading" class="space-y-3">
      <div
        v-for="i in 5"
        :key="i"
        class="h-20 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 animate-pulse"
      />
    </div>

    <div v-else-if="!restaurants.length" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 p-8 text-center">
      <p class="text-slate-500 dark:text-slate-400">No restaurants found.</p>
    </div>

    <div v-else class="space-y-3" data-testid="superadmin-restaurants-list">
      <div
        v-for="r in restaurants"
        :key="r.uuid"
        class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 overflow-hidden"
      >
        <div class="flex gap-4 p-4 lg:p-5 min-h-[44px]">
          <div class="w-12 h-12 lg:w-14 lg:h-14 rounded-xl bg-sage/10 dark:bg-sage/20 shrink-0 overflow-hidden flex items-center justify-center ring-1 ring-slate-200/50 dark:ring-slate-700">
            <img
              v-if="r.logo_url"
              :src="r.logo_url"
              :alt="r.name"
              class="w-full h-full object-cover"
            />
            <span v-else class="material-icons text-xl lg:text-2xl text-sage dark:text-sage/80">restaurant</span>
          </div>
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-charcoal dark:text-white truncate">{{ r.name }}</p>
            <p v-if="r.slug" class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ r.slug }}</p>
            <p v-if="r.address" class="text-sm text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ r.address }}</p>
            <p v-else-if="r.phone" class="text-sm text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ r.phone }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { superadminService } from '@/services'
import { normalizeApiError } from '@/services'

const restaurants = ref([])
const loading = ref(true)
const error = ref('')

async function loadRestaurants() {
  error.value = ''
  loading.value = true
  try {
    restaurants.value = await superadminService.listRestaurants()
  } catch (err) {
    const normalized = normalizeApiError(err)
    error.value = normalized.status === 403 ? "You don't have permission to view restaurants." : normalized.message
  } finally {
    loading.value = false
  }
}

onMounted(() => loadRestaurants())
</script>
