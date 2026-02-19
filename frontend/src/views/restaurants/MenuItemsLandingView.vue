<template>
  <div>
    <div v-if="loading" class="space-y-4">
      <div class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-32 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>
    <div v-else-if="restaurants.length === 1" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-6 text-center">
      <p class="text-slate-500 dark:text-slate-400 mb-4">Redirecting to menu…</p>
    </div>
    <div v-else-if="!restaurants.length" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center">
      <p class="text-slate-500 dark:text-slate-400 mb-4">Create a restaurant first to manage menus and items.</p>
      <router-link to="/app/restaurants/new">
        <AppButton variant="primary" class="min-h-[44px]">Add restaurant</AppButton>
      </router-link>
    </div>
    <div v-else class="space-y-4">
      <header>
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Menu items</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Select a restaurant to manage its menus, categories and items.</p>
      </header>
      <ul class="space-y-2">
        <li
          v-for="r in restaurants"
          :key="r.uuid"
          class="flex items-center gap-3 p-4 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800"
        >
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-charcoal dark:text-white">{{ r.name }}</p>
          </div>
          <router-link :to="{ name: 'RestaurantDetail', params: { uuid: r.uuid }, query: { tab: 'menu' } }">
            <AppButton variant="primary" size="sm" class="min-h-[44px]">Manage menu</AppButton>
          </router-link>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import Restaurant from '@/models/Restaurant.js'
import { restaurantService, normalizeApiError } from '@/services'

const router = useRouter()
const loading = ref(true)
const restaurants = ref([])

onMounted(async () => {
  try {
    const res = await restaurantService.list({ per_page: 50 })
    const list = (res.data ?? []).map((r) => Restaurant.fromApi({ data: r }).toJSON())
    restaurants.value = list
    if (list.length === 1) {
      router.replace({ name: 'RestaurantDetail', params: { uuid: list[0].uuid }, query: { tab: 'menu' } })
    }
  } catch (e) {
    console.error(normalizeApiError(e).message)
    restaurants.value = []
  } finally {
    loading.value = false
  }
})
</script>
