<template>
  <div>
    <div v-if="loading && !restaurant" class="space-y-4">
      <div class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-32 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="!restaurant"
      class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant not found.</p>
      <AppBackLink :to="backLink" />
    </div>

    <template v-else>
      <header class="mb-4 lg:mb-6">
        <AppBackLink :to="backLink" />
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl mt-3">
          {{ categoryDisplayName }}
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Add and reorder menu items in this category. Drag to change order.
        </p>
      </header>

      <div
        v-if="error"
        role="alert"
        class="mb-6 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
      >
        {{ error }}
      </div>

      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 p-4 lg:p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ items.length }} item(s)</span>
          <AppButton
            v-if="restaurant"
            variant="primary"
            class="min-h-[44px] w-full sm:w-auto"
            @click="openAddItemModal"
          >
            <span class="material-icons mr-1">add</span>
            Add menu item
          </AppButton>
        </div>

        <div v-if="itemsLoading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-16 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
        </div>
        <div v-else-if="!items.length" class="py-8 text-center">
          <p class="text-slate-500 dark:text-slate-400 mb-4">No menu items in this category yet.</p>
          <AppButton
            v-if="restaurant"
            variant="primary"
            class="min-h-[44px]"
            @click="openAddItemModal"
          >
            <span class="material-icons mr-1">add</span>
            Add menu item
          </AppButton>
        </div>
        <draggable
          v-else
          v-model="items"
          item-key="uuid"
          handle=".item-drag-handle"
          class="space-y-2"
          :animation="200"
          @end="onReorderItems"
        >
          <template #item="{ element: item }">
            <li class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-slate-700 shadow-sm list-none min-h-[44px]">
              <button
                type="button"
                class="item-drag-handle p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 min-h-[44px] min-w-[44px] flex items-center justify-center touch-none"
                aria-label="Drag to reorder"
              >
                <span class="material-icons">drag_indicator</span>
              </button>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-charcoal dark:text-white truncate">{{ itemDisplayName(item) }}</p>
                <p v-if="itemPriceDisplay(item)" class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ itemPriceDisplay(item) }}</p>
              </div>
              <router-link
                :to="{ name: 'RestaurantMenuItemEdit', params: { uuid: restaurant.uuid, itemUuid: item.uuid } }"
                class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 text-slate-600 dark:text-slate-300 shrink-0"
                aria-label="Edit menu item"
              >
                <span class="material-icons">edit</span>
              </router-link>
            </li>
          </template>
        </draggable>
      </div>

      <!-- Add menu item to category: modal (MVP: existing restaurant items only; catalog from GET /api/menu-items not wired here) -->
      <AppModal
        :open="addItemModalOpen"
        title="Add menu item to category"
        description="Choose a menu item to add to this category. Existing restaurant items can be moved here."
        @close="closeAddItemModal"
      >
        <div class="space-y-4">
          <div>
            <label for="add-item-search" class="block text-sm font-medium text-charcoal dark:text-white mb-1">Search</label>
            <input
              id="add-item-search"
              v-model="addItemSearchQuery"
              type="search"
              autocomplete="off"
              placeholder="Filter by name…"
              class="w-full min-h-[44px] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-4 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
              aria-label="Filter menu items by name"
            />
          </div>
          <div>
            <span class="block text-sm font-medium text-charcoal dark:text-white mb-2">Show</span>
            <div class="flex flex-col sm:flex-row rounded-xl border border-slate-200 dark:border-slate-700 p-1 gap-1" role="group" aria-label="Filter by added status">
              <button
                type="button"
                class="flex-1 min-h-[44px] rounded-lg text-sm font-medium transition-colors w-full sm:w-auto"
                :class="addItemFilterMode === 'not_added' ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-700'"
                :aria-pressed="addItemFilterMode === 'not_added'"
                @click="addItemFilterMode = 'not_added'"
              >
                Not in this category
              </button>
              <button
                type="button"
                class="flex-1 min-h-[44px] rounded-lg text-sm font-medium transition-colors w-full sm:w-auto"
                :class="addItemFilterMode === 'added' ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-700'"
                :aria-pressed="addItemFilterMode === 'added'"
                @click="addItemFilterMode = 'added'"
              >
                In this category
              </button>
            </div>
          </div>
          <p v-if="addItemModalError" role="alert" class="text-sm text-red-600 dark:text-red-400">
            {{ addItemModalError }}
          </p>
          <div v-if="addItemModalLoading" class="py-6 text-center text-slate-500 dark:text-slate-400 text-sm">
            Loading menu items…
          </div>
          <div v-else class="max-h-[50vh] overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-200 dark:divide-slate-700">
            <div
              v-for="menuItem in addItemFilteredList"
              :key="menuItem.uuid"
              class="flex items-center gap-3 p-3 min-h-[44px]"
            >
              <div class="min-w-0 flex-1">
                <p class="font-medium text-charcoal dark:text-white truncate">{{ itemDisplayName(menuItem) }}</p>
                <p v-if="itemPriceDisplay(menuItem)" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ itemPriceDisplay(menuItem) }}</p>
              </div>
              <AppButton
                v-if="addItemFilterMode === 'not_added'"
                variant="primary"
                size="sm"
                class="min-h-[44px] min-w-[44px] shrink-0"
                :disabled="addItemAddingUuid === menuItem.uuid"
                :aria-label="`Add ${itemDisplayName(menuItem)} to category`"
                @click="addItemToCategory(menuItem)"
              >
                <span v-if="addItemAddingUuid === menuItem.uuid" class="material-icons animate-spin text-lg">sync</span>
                <span v-else class="material-icons text-lg">add</span>
              </AppButton>
            </div>
            <p v-if="!addItemModalLoading && addItemFilteredList.length === 0" class="p-4 text-sm text-slate-500 dark:text-slate-400 text-center">
              {{ addItemFilterMode === 'not_added' ? 'No other menu items to add, or none match the search.' : 'No items in this category match the search.' }}
            </p>
          </div>
        </div>
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" @click="closeAddItemModal">Done</AppButton>
        </template>
      </AppModal>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import AppBackLink from '@/components/AppBackLink.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import Restaurant from '@/models/Restaurant.js'
import MenuItem from '@/models/MenuItem.js'
import { restaurantService, normalizeApiError } from '@/services'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const breadcrumbStore = useBreadcrumbStore()
const toastStore = useToastStore()

const uuid = computed(() => route.params.uuid)
const categoryUuid = computed(() => route.params.categoryUuid)
const categoryNameFromQuery = computed(() => route.query.name ?? null)

const loading = ref(true)
const itemsLoading = ref(false)
const restaurant = ref(null)
const items = ref([])
const error = ref('')

const defaultLocale = computed(() => restaurant.value?.default_locale ?? 'en')

const backLink = computed(() =>
  restaurant.value
    ? { name: 'RestaurantDetail', params: { uuid: restaurant.value.uuid }, query: { tab: 'menu' } }
    : { name: 'Restaurants' }
)

const categoryDisplayName = computed(() => {
  const name = categoryNameFromQuery.value
  if (name && typeof name === 'string') return decodeURIComponent(name)
  return 'Menu items'
})

// Add-item modal state
const addItemModalOpen = ref(false)
const addItemSearchQuery = ref('')
const addItemFilterMode = ref('not_added') // 'not_added' | 'added'
const addItemModalLoading = ref(false)
const addItemModalError = ref('')
const addItemAddingUuid = ref(null)
const allMenuItemsForModal = ref([]) // raw MenuItem.toJSON() from listMenuItems

const addItemInCategory = computed(() => {
  const cat = categoryUuid.value
  if (!cat) return []
  return allMenuItemsForModal.value.filter((i) => (i.category_uuid ?? null) === cat)
})

const addItemNotInCategory = computed(() => {
  const cat = categoryUuid.value
  if (!cat) return []
  return allMenuItemsForModal.value.filter((i) => (i.category_uuid ?? null) !== cat)
})

const addItemFilteredList = computed(() => {
  const list = addItemFilterMode.value === 'added' ? addItemInCategory.value : addItemNotInCategory.value
  const q = (addItemSearchQuery.value ?? '').trim().toLowerCase()
  if (!q) return list
  return list.filter((item) => {
    const name = itemDisplayName(item)
    return name.toLowerCase().includes(q)
  })
})

function openAddItemModal() {
  addItemSearchQuery.value = ''
  addItemFilterMode.value = 'not_added'
  addItemModalError.value = ''
  addItemAddingUuid.value = null
  addItemModalOpen.value = true
  fetchAllMenuItemsForModal()
}

function closeAddItemModal() {
  addItemModalOpen.value = false
  addItemModalError.value = ''
  addItemAddingUuid.value = null
}

async function fetchAllMenuItemsForModal() {
  if (!uuid.value) return
  addItemModalLoading.value = true
  addItemModalError.value = ''
  try {
    const res = await restaurantService.listMenuItems(uuid.value)
    const list = Array.isArray(res?.data) ? res.data : []
    allMenuItemsForModal.value = list.map((i) => MenuItem.fromApi({ data: i }).toJSON())
  } catch (e) {
    addItemModalError.value = normalizeApiError(e).message
    allMenuItemsForModal.value = []
  } finally {
    addItemModalLoading.value = false
  }
}

async function addItemToCategory(menuItem) {
  if (!uuid.value || !categoryUuid.value || !menuItem?.uuid || addItemAddingUuid.value) return
  addItemAddingUuid.value = menuItem.uuid
  addItemModalError.value = ''
  const nextSortOrder = addItemInCategory.value.length
  try {
    await restaurantService.updateMenuItem(uuid.value, menuItem.uuid, {
      category_uuid: categoryUuid.value,
      sort_order: nextSortOrder,
    })
    restaurantService.invalidateMenuItemsCache(uuid.value)
    await loadItems()
    await fetchAllMenuItemsForModal()
    toastStore.success('Menu item added to category.')
  } catch (e) {
    addItemModalError.value = normalizeApiError(e).message
  } finally {
    addItemAddingUuid.value = null
  }
}

function itemDisplayName(item) {
  const loc = defaultLocale.value
  const t = item?.translations?.[loc] ?? item?.translations?.en ?? {}
  return t?.name?.trim() || '—'
}

function itemPriceDisplay(item) {
  const p = item?.price
  if (p == null || p === '') return ''
  const n = Number(p)
  if (Number.isNaN(n)) return ''
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', minimumFractionDigits: 2 }).format(n)
}

async function loadRestaurant() {
  if (!uuid.value) return
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    if (restaurant.value) {
      breadcrumbStore.setRestaurantName(restaurant.value.name ?? null)
      breadcrumbStore.setMenuName(route.query.menu ?? null)
      breadcrumbStore.setCategoryName((categoryDisplayName.value || route.query.name) ?? null)
    }
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else error.value = normalizeApiError(e).message
  } finally {
    loading.value = false
  }
}

async function loadItems() {
  if (!uuid.value || !categoryUuid.value) return
  itemsLoading.value = true
  error.value = ''
  try {
    const res = await restaurantService.listMenuItems(uuid.value)
    const list = Array.isArray(res?.data) ? res.data : []
    items.value = list
      .filter((i) => (i.category_uuid ?? null) === categoryUuid.value)
      .map((i) => MenuItem.fromApi({ data: i }).toJSON())
  } catch (e) {
    error.value = normalizeApiError(e).message
    items.value = []
  } finally {
    itemsLoading.value = false
  }
}

async function onReorderItems() {
  if (!uuid.value || !categoryUuid.value) return
  const order = items.value.map((i) => i.uuid)
  try {
    await restaurantService.reorderMenuItems(uuid.value, categoryUuid.value, order)
    error.value = ''
    toastStore.success('Order updated.')
  } catch (e) {
    error.value = normalizeApiError(e).message
    await loadItems()
  }
}

onMounted(async () => {
  await loadRestaurant()
  if (restaurant.value) await loadItems()
})

watch([uuid, categoryUuid], async () => {
  if (restaurant.value) await loadItems()
})

watch(
  () => [route.query.menu, route.query.name],
  () => {
    if (restaurant.value) {
      breadcrumbStore.setMenuName(route.query.menu ?? null)
      breadcrumbStore.setCategoryName((categoryDisplayName.value || route.query.name) ?? null)
    }
  },
  { immediate: false }
)
</script>
