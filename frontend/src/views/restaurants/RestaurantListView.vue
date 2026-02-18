<template>
  <div class="-mx-4 px-4 py-2 -my-2 lg:mx-0 lg:px-0 lg:py-0 lg:my-0 bg-cream/40 dark:bg-sage/5 rounded-2xl lg:rounded-none min-h-[60vh] lg:min-h-0">
    <header class="mb-6 lg:mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <span v-if="!embed" class="text-xs font-semibold uppercase tracking-wider text-primary/80 dark:text-primary/90">Locations</span>
        <h2 class="text-2xl font-bold tracking-tight text-charcoal dark:text-white lg:text-3xl mt-0.5">
          {{ embed ? 'Manage restaurants' : 'Your restaurants' }}
        </h2>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
          {{ embed ? 'Switch to another location or add a new one.' : 'Manage menus and details for each location.' }}
        </p>
      </div>
      <router-link to="/app/restaurants/new" class="inline-flex shrink-0">
        <AppButton variant="primary" class="w-full sm:w-auto justify-center py-3 min-h-[44px] shadow-lg shadow-primary/20">
          <template #icon>
            <span class="material-icons">add</span>
          </template>
          Add restaurant
        </AppButton>
      </router-link>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div
        v-for="i in 3"
        :key="i"
        class="h-20 lg:h-24 rounded-2xl bg-white/60 dark:bg-zinc-800/80 border border-slate-100 dark:border-slate-700/50 animate-pulse"
      />
    </div>

    <!-- Empty state: restaurant-specific, not generic card -->
    <div
      v-else-if="!restaurants.length"
      class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sage/15 via-cream/80 to-primary/5 dark:from-sage/20 dark:via-zinc-900 dark:to-primary/10 border border-sage/20 dark:border-sage/30 py-12 lg:py-16 px-6 text-center"
    >
      <!-- Decorative: simple “plate” circles (restaurant feel) -->
      <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
        <div class="absolute top-1/4 left-1/4 w-32 h-32 rounded-full border-2 border-sage/20 dark:border-sage/30 -translate-x-1/2 -translate-y-1/2" />
        <div class="absolute bottom-1/4 right-1/4 w-24 h-24 rounded-full border-2 border-primary/15 -translate-x-1/2 translate-y-1/2" />
      </div>
      <div class="relative">
        <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl mb-2">Add your first location</h3>
        <p class="text-slate-600 dark:text-slate-400 text-sm max-w-md mx-auto mb-8">
          Create a restaurant to get your menu online, share your address and hours, and let customers find you.
        </p>
        <router-link to="/app/restaurants/new">
          <AppButton variant="primary" class="min-h-[48px] px-6 shadow-lg shadow-primary/20 transition-transform hover:scale-[1.02] active:scale-[0.98]">
            <template #icon>
              <span class="material-icons">restaurant</span>
            </template>
            Create restaurant
          </AppButton>
        </router-link>
      </div>
    </div>

    <!-- List -->
    <div v-else class="space-y-3">
      <router-link
        v-for="r in restaurants"
        :key="r.uuid"
        :to="{ name: 'RestaurantDetail', params: { uuid: r.uuid } }"
        class="group block rounded-2xl overflow-hidden transition-all duration-200 ease-out min-h-[44px] bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-md hover:shadow-slate-200/50 dark:hover:shadow-zinc-900/50"
      >
        <div class="flex gap-4 p-4 lg:p-5">
          <div class="w-14 h-14 lg:w-16 lg:h-16 rounded-xl bg-sage/10 dark:bg-sage/20 shrink-0 overflow-hidden flex items-center justify-center ring-1 ring-slate-200/50 dark:ring-slate-700">
            <img
              v-if="r.logo_url"
              :src="r.logo_url"
              :alt="r.name"
              class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
            />
            <span v-else class="material-icons text-2xl lg:text-3xl text-sage dark:text-sage/80">restaurant</span>
          </div>
          <div class="min-w-0 flex-1">
            <h3 class="font-semibold text-charcoal dark:text-white truncate group-hover:text-primary transition-colors">{{ r.name }}</h3>
            <p v-if="r.address" class="text-sm text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ r.address }}</p>
            <p v-else-if="r.phone" class="text-sm text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ r.phone }}</p>
          </div>
          <span class="material-icons text-slate-400 group-hover:text-primary group-hover:translate-x-0.5 self-center shrink-0 transition-all duration-200">chevron_right</span>
        </div>
      </router-link>

      <!-- Pagination -->
      <div
        v-if="meta && meta.last_page > 1"
        class="flex flex-wrap items-center justify-center gap-2 pt-6"
        role="navigation"
        aria-label="Pagination"
      >
        <AppButton
          variant="secondary"
          size="sm"
          :disabled="meta.current_page <= 1"
          @click="goToPage(meta.current_page - 1)"
        >
          Previous
        </AppButton>
        <span class="text-sm text-slate-500 dark:text-slate-400 px-2" aria-live="polite">
          Page {{ meta.current_page }} of {{ meta.last_page }}<template v-if="meta.total != null"> · {{ meta.total }} restaurant{{ meta.total === 1 ? '' : 's' }}</template>
        </span>
        <AppButton
          variant="secondary"
          size="sm"
          :disabled="meta.current_page >= meta.last_page"
          @click="goToPage(meta.current_page + 1)"
        >
          Next
        </AppButton>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  embed: { type: Boolean, default: false },
})
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import Restaurant from '@/models/Restaurant.js'
import { restaurantService, normalizeApiError } from '@/services'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const restaurants = ref([])
const meta = ref(null)

async function fetchList(page = 1) {
  loading.value = true
  try {
    const res = await restaurantService.list({ per_page: 15, page })
    const items = res.data ?? []
    restaurants.value = items.map((r) => Restaurant.fromApi(r).toJSON())
    meta.value = res.meta ?? null
  } catch (e) {
    const { message } = normalizeApiError(e)
    console.error(message)
    restaurants.value = []
    meta.value = null
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (!meta.value || page < 1 || page > meta.value.last_page) return
  router.replace({ query: { ...route.query, page } })
}

// Refetch when navigating to this list (including when coming back from form) so empty state is correct
watch(
  () => ({ name: route.name, page: route.query.page }),
  ({ name, page }) => {
    if (name !== 'Restaurants') return
    const num = Math.max(1, parseInt(page, 10) || 1)
    fetchList(num)
  },
  { immediate: true }
)
</script>
