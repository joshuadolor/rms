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
            class="hover:text-primary transition-colors truncate max-w-[12rem] sm:max-w-none"
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
const { restaurantName, menuItemName } = storeToRefs(breadcrumbStore)

const trail = computed(() => {
  const name = route.name
  if (!name || !BREADCRUMB_CONFIG[name]) return []
  // Depend on store labels so trail updates when restaurant/menu item name loads
  void restaurantName.value
  void menuItemName.value
  const raw = getBreadcrumbTrail(name, breadcrumbStore)
  return raw.map((seg, index) => {
    const isLast = index === raw.length - 1
    const resolved = router.resolve({ name: seg.name, params: route.params })
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
  },
  { immediate: true }
)
</script>
