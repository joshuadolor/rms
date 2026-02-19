<template>
  <nav v-if="trail.length" aria-label="Breadcrumb" class="mb-4 lg:mb-6">
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
      <li v-for="(segment, i) in trail" :key="segment.name" class="flex items-center gap-1.5">
        <template v-if="i > 0">
          <span class="text-slate-300 dark:text-slate-600 select-none" aria-hidden="true">/</span>
        </template>
        <template v-if="i === trail.length - 1">
          <span class="font-medium text-charcoal dark:text-white">{{ segment.label }}</span>
        </template>
        <template v-else>
          <router-link
            :to="segment.to"
            class="inline-flex items-center min-h-[44px] py-2 -my-2 hover:text-primary transition-colors truncate max-w-[12rem] sm:max-w-none"
          >
            {{ segment.label }}
          </router-link>
        </template>
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import { BREADCRUMB_CONFIG, getBreadcrumbTrail } from '@/config/breadcrumbs'

const route = useRoute()
const router = useRouter()
const breadcrumbStore = useBreadcrumbStore()
const { restaurantName, menuName, categoryName, menuItemName } = storeToRefs(breadcrumbStore)

const trail = computed(() => {
  const name = route.name
  if (!name || !BREADCRUMB_CONFIG[name]) return []
  // Depend on store labels so trail updates when names load
  void restaurantName.value
  void menuName.value
  void categoryName.value
  void menuItemName.value
  const raw = getBreadcrumbTrail(name, breadcrumbStore, route)
  const restaurantMenuQuery = { ...route.query, tab: 'menu' }
  return raw.map((seg, index) => {
    const isLast = index === raw.length - 1
    let resolved
    if (seg.name === '__main_menu__' || seg.name === 'RestaurantMenuItems' || seg.name === '__category_label__') {
      resolved = router.resolve({ name: 'RestaurantDetail', params: { uuid: route.params.uuid }, query: restaurantMenuQuery })
    } else if (seg.name === '__category__') {
      resolved = router.resolve({ name: 'CategoryMenuItems', params: route.params, query: route.query })
    } else {
      resolved = router.resolve({ name: seg.name, params: route.params, query: route.query })
    }
    return {
      name: seg.name,
      label: seg.label,
      to: isLast ? undefined : resolved,
    }
  })
})

watch(
  () => route.name,
  (name) => {
    if (name === 'Restaurants') breadcrumbStore.clearRestaurant()
    if (name === 'RestaurantDetail') breadcrumbStore.clearMenuAndCategory()
  },
  { immediate: true }
)
</script>
