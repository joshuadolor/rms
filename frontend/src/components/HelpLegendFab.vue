<template>
  <div v-if="inRestaurantContext" class="contents">
    <!-- Same size as other FABs (56px). Info color. Bottom-right when no page FAB; left of page FAB when present. -->
    <button
      type="button"
      class="help-legend-fab fixed z-20 w-14 h-14 min-h-[56px] min-w-[56px] rounded-full bg-sky-500 text-white shadow-lg hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 flex items-center justify-center transition-transform hover:scale-105 active:scale-95 motion-reduce:transition-none"
      :class="fabPositionClasses"
      aria-label="Show help: what do the buttons mean?"
      title="Help"
      data-testid="help-legend-button"
      @click="helpModalOpen = true"
    >
      <span class="material-icons text-3xl">help_outline</span>
    </button>

    <AppModal
      :open="helpModalOpen"
      title="Help"
      :description="modalDescription"
      data-testid="help-legend-modal"
      @close="helpModalOpen = false"
    >
      <ul class="space-y-3" aria-label="Button legend">
        <li
          v-for="(entry, index) in LEGEND"
          :key="index"
          class="flex items-start gap-3"
        >
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-zinc-800 text-slate-600 dark:text-slate-300"
            aria-hidden="true"
          >
            <span class="material-icons">{{ entry.icon }}</span>
          </span>
          <div>
            <span class="font-medium text-charcoal dark:text-white">{{ entry.label }}</span>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ entry.description }}</p>
          </div>
        </li>
      </ul>
      <template #footer>
        <AppButton variant="primary" class="min-h-[44px]" @click="helpModalOpen = false">
          Close
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { usePageFabStore } from '@/stores/pageFab'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'

/** Single static legend for all restaurant-context pages. */
const LEGEND = [
  { icon: 'drag_indicator', label: 'Drag handle', description: 'Drag to reorder items or categories.' },
  { icon: 'schedule', label: 'Schedule', description: 'Set when this item or category is available (e.g. lunch only).' },
  { icon: 'check_circle', label: 'Available / Not available', description: 'Mark as available or not on the public menu.' },
  { icon: 'visibility', label: 'Visible / Hidden', description: 'Show or hide on the public menu.' },
  { icon: 'label', label: 'Assign tags', description: 'Assign tags (e.g. Spicy, Vegan) to a menu item.' },
  { icon: 'edit', label: 'Edit', description: 'Edit name or details.' },
  { icon: 'restaurant_menu', label: 'Manage items', description: 'Open a category to add and reorder menu items.' },
  { icon: 'delete', label: 'Remove', description: 'Remove this category or item from the menu.' },
]

const MODAL_DESCRIPTION = 'Meaning of the icons you see on menu and category rows in restaurant management.'

const route = useRoute()
const pageFabStore = usePageFabStore()
const { hasPageFab } = storeToRefs(pageFabStore)

const helpModalOpen = ref(false)

const inRestaurantContext = computed(() =>
  route.path.startsWith('/app/restaurants')
)

/** Bottom-right when no page FAB; left of page FAB when present. */
const fabPositionClasses = computed(() => {
  const bottom = 'bottom-[max(1.5rem,env(safe-area-inset-bottom))]'
  const right = hasPageFab.value ? 'right-[7rem]' : 'right-6'
  return `${bottom} ${right}`
})

const modalDescription = computed(() => MODAL_DESCRIPTION)

watch(inRestaurantContext, (inContext) => {
  if (!inContext) helpModalOpen.value = false
})
</script>
