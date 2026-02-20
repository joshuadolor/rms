<template>
  <div class="-mx-4 px-4 py-2 -my-2 lg:mx-0 lg:px-0 lg:py-0 lg:my-0 min-h-[60vh] lg:min-h-0">
    <header class="mb-6 lg:mb-8">
      <span class="text-xs font-semibold uppercase tracking-wider text-primary/80 dark:text-primary/90">Reviews</span>
      <h2 class="text-2xl font-bold tracking-tight text-charcoal dark:text-white lg:text-3xl mt-0.5">
        Feedbacks
      </h2>
      <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
        View and moderate customer feedback for each restaurant.
      </p>
    </header>

    <div v-if="loading" class="space-y-3">
      <div
        v-for="i in 3"
        :key="i"
        class="h-20 lg:h-24 rounded-2xl bg-white/60 dark:bg-zinc-800/80 border border-slate-100 dark:border-slate-700/50 animate-pulse"
      />
    </div>

    <div
      v-else-if="!restaurants.length"
      class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <span class="material-icons text-5xl text-slate-300 dark:text-slate-600">rate_review</span>
      <p class="mt-4 text-slate-500 dark:text-slate-400">Create a restaurant first to receive and manage feedbacks.</p>
      <router-link to="/app/restaurants/new" class="mt-6 inline-block">
        <AppButton variant="primary" class="min-h-[44px]">Add restaurant</AppButton>
      </router-link>
    </div>

    <div v-else-if="restaurants.length === 1" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-6 text-center">
      <p class="text-slate-500 dark:text-slate-400 mb-4">Redirecting to feedbacks…</p>
    </div>

    <div v-else class="space-y-3">
      <router-link
        v-for="r in restaurants"
        :key="r.uuid"
        :to="{ name: 'FeedbacksList', params: { restaurantUuid: r.uuid } }"
        class="group block rounded-2xl overflow-hidden transition-all duration-200 min-h-[44px] bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-md"
      >
        <div class="flex gap-4 p-4 lg:p-5">
          <div class="w-14 h-14 lg:w-16 lg:h-16 rounded-xl bg-sage/10 dark:bg-sage/20 shrink-0 overflow-hidden flex items-center justify-center ring-1 ring-slate-200/50 dark:ring-slate-700">
            <img
              v-if="r.logo_url"
              :src="r.logo_url"
              :alt="r.name"
              class="w-full h-full object-cover"
            />
            <span v-else class="material-icons text-2xl lg:text-3xl text-sage dark:text-sage/80">restaurant</span>
          </div>
          <div class="min-w-0 flex-1">
            <h3 class="font-semibold text-charcoal dark:text-white truncate group-hover:text-primary transition-colors">{{ r.name }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">View &amp; moderate feedbacks</p>
          </div>
          <span class="material-icons text-slate-400 group-hover:text-primary self-center shrink-0 transition-colors">chevron_right</span>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import Restaurant from '@/models/Restaurant.js'
import { restaurantService, normalizeApiError } from '@/services'

const router = useRouter()
const loading = ref(true)
const restaurants = ref([])

async function fetchRestaurants() {
  loading.value = true
  try {
    const res = await restaurantService.list({ per_page: 50 })
    const list = (res.data ?? []).map((r) => Restaurant.fromApi(r).toJSON())
    restaurants.value = list
    if (list.length === 1) {
      router.replace({ name: 'FeedbacksList', params: { restaurantUuid: list[0].uuid } })
    }
  } catch (e) {
    console.error(normalizeApiError(e).message)
    restaurants.value = []
  } finally {
    loading.value = false
  }
}

onMounted(fetchRestaurants)

watch(
  () => router.currentRoute.value.name,
  (name) => {
    if (name === 'Feedbacks') fetchRestaurants()
  }
)
</script>
