<template>
  <div>
    <div v-if="loading && !restaurant" class="space-y-4">
      <div class="h-24 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-32 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="!restaurant"
      class="rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">{{ $t('app.restaurantNotFound') }}</p>
      <AppBackLink :to="backLink" />
    </div>

    <template v-else>
      <header class="mb-6 lg:mb-8">
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

      <!-- Empty state: no white card when no items -->
      <div
        v-if="!itemsLoading && !items.length"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sage/15 via-cream/80 to-primary/5 dark:from-sage/20 dark:via-zinc-900 dark:to-primary/10 border border-sage/20 dark:border-sage/30 py-12 lg:py-16 px-6 text-center"
      >
        <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
          <div class="absolute top-1/4 left-1/4 w-32 h-32 rounded-full border-2 border-sage/20 dark:border-sage/30 -translate-x-1/2 -translate-y-1/2" />
          <div class="absolute bottom-1/4 right-1/4 w-24 h-24 rounded-full border-2 border-primary/15 -translate-x-1/2 translate-y-1/2" />
        </div>
        <div class="relative">
          <h3 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl mb-2">Add menu items to this category</h3>
          <p class="text-slate-600 dark:text-slate-400 text-sm max-w-md mx-auto mb-8">
            Add existing menu items from this restaurant to show them in this category. You can reorder them after adding.
          </p>
          <AppButton
            v-if="restaurant"
            variant="primary"
            class="min-h-[48px] px-6 shadow-lg shadow-primary/20 transition-transform hover:scale-[1.02] active:scale-[0.98]"
            @click="openAddItemModal"
          >
            <template #icon>
              <span class="material-icons">restaurant_menu</span>
            </template>
            Add menu item
          </AppButton>
        </div>
      </div>

      <!-- Loading items (no white card when empty) -->
      <div v-else-if="itemsLoading" class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-16 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      </div>

      <!-- Has items: white card with count, Add button, draggable list -->
      <div
        v-else
        class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 p-4 lg:p-6 space-y-4"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ items.length }} item(s)</span>
          <AppButton
            v-if="restaurant"
            variant="primary"
            class="min-h-[44px] w-full sm:w-auto"
            @click="openAddItemModal"
          >
            <template #icon>
              <span class="material-icons">add</span>
            </template>
            Add menu item
          </AppButton>
        </div>
        <draggable
          v-model="items"
          item-key="uuid"
          handle=".item-drag-handle"
          class="space-y-2"
          :animation="200"
          @end="onReorderItems"
        >
          <template #item="{ element: item }">
            <li class="flex flex-wrap list-none min-h-[44px] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm bg-slate-50 dark:bg-zinc-800/50">
              <div
                class="item-drag-handle flex items-center justify-center w-12 shrink-0 self-stretch rounded-l-xl touch-none cursor-grab active:cursor-grabbing text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 bg-slate-100 dark:bg-zinc-800/80 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary"
                title="Drag to reorder"
                aria-label="Drag to reorder"
                role="button"
                tabindex="0"
              >
                <span class="material-icons">drag_indicator</span>
              </div>
              <div class="flex flex-wrap items-center gap-3 flex-1 min-w-0 p-4">
                <div class="min-w-0 flex-1">
                  <div v-if="item.tags?.length" class="flex flex-wrap gap-1.5 mb-1.5">
                    <span
                      v-for="tag in item.tags"
                      :key="tag.uuid"
                      class="tag-tooltip-host relative inline-flex items-center justify-center min-w-[36px] min-h-[36px] rounded-md shrink-0 cursor-pointer motion-reduce:transition-none transition-opacity duration-150"
                      :style="{ backgroundColor: tag.color ? `${tag.color}20` : undefined, color: tag.color || '#6b7280' }"
                      :aria-label="(tag.text || 'Tag') + ' (assigned)'"
                      tabindex="0"
                      @mouseenter="tagHoveredUuid = tag.uuid"
                      @mouseleave="tagHoveredUuid = null"
                      @focus="tagHoveredUuid = tag.uuid"
                      @blur="tagHoveredUuid = null"
                    >
                      <span v-if="tag.icon" class="material-icons text-xxl">{{ tag.icon }}</span>
                      <span v-else class="material-icons text-sm">label</span>
                      <span
                        class="tag-tooltip absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 px-2 py-1 text-xs font-medium rounded-md bg-slate-800 text-white whitespace-nowrap pointer-events-none z-[100] shadow-lg motion-reduce:transition-none transition-opacity duration-150"
                        :class="tagHoveredUuid === tag.uuid ? 'opacity-100' : 'opacity-0'"
                      >
                        {{ tag.text || 'Tag' }}
                      </span>
                    </span>
                  </div>
                  <p class="font-medium text-charcoal dark:text-white break-words sm:truncate">{{ itemDisplayName(item) }}</p>
                  <p v-if="itemPriceDisplay(item)" class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ itemPriceDisplay(item) }}</p>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0 w-full sm:w-auto">
                <button
                  type="button"
                  class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-slate-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 hover:bg-slate-50 dark:hover:bg-zinc-700"
                  title="Set availability times"
                  aria-label="Set availability times for this menu item"
                  @click="openAvailabilityModal(item)"
                >
                  <span class="material-icons">schedule</span>
                </button>
                <button
                  type="button"
                  class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border transition-colors disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                  :class="itemIsAvailable(item) ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'"
                  :title="itemIsAvailable(item) ? 'Available' : 'Not available'"
                  :aria-label="(itemIsAvailable(item) ? 'Mark not available' : 'Mark available') + ' on public menu'"
                  :disabled="togglingAvailabilityUuid === item.uuid"
                  @click="toggleItemAvailability(item)"
                >
                  <span v-if="togglingAvailabilityUuid === item.uuid" class="material-icons text-xl animate-spin">sync</span>
                  <span v-else class="material-icons text-xl">{{ itemIsAvailable(item) ? 'check_circle' : 'cancel' }}</span>
                </button>
                <button
                  type="button"
                  class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border transition-colors disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                  :class="itemIsActive(item) ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-zinc-800 border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400'"
                  :title="itemIsActive(item) ? 'Visible on menu' : 'Hidden from menu'"
                  :aria-label="(itemIsActive(item) ? 'Hide' : 'Show') + ' on public menu'"
                  :disabled="togglingItemUuid === item.uuid"
                  @click="toggleItemVisibility(item)"
                >
                  <span v-if="togglingItemUuid === item.uuid" class="material-icons text-xl animate-spin">sync</span>
                  <span v-else class="material-icons text-xl">{{ itemIsActive(item) ? 'visibility' : 'visibility_off' }}</span>
                </button>
                <button
                  type="button"
                  class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-slate-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 hover:bg-slate-50 dark:hover:bg-zinc-700"
                  title="Assign tags"
                  aria-label="Assign tags to this menu item"
                  @click="openAssignTagsModal(item)"
                >
                  <span class="material-icons">label</span>
                </button>
                <router-link
                  :to="{ name: 'RestaurantMenuItemEdit', params: { uuid: restaurant.uuid, itemUuid: item.uuid } }"
                  class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 text-slate-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                  title="Edit menu item"
                  aria-label="Edit menu item"
                >
                  <span class="material-icons">edit</span>
                </router-link>
              </div>
              </div>
            </li>
          </template>
        </draggable>
      </div>

      <!-- Add menu item to category: single list of addable items, each with Add button -->
      <AppModal
        :open="addItemModalOpen"
        title="Add menu item to category"
        description="Add or remove items in this category. You can add from this restaurant or from your catalog. Search to filter."
        @close="closeAddItemModal"
      >
        <div class="space-y-4">
          <AppInput
            v-model="addItemSearchQuery"
            type="search"
            label="Search"
            placeholder="Filter by name…"
            autocomplete="off"
          />
          <p v-if="addItemModalError" role="alert" class="text-sm text-red-600 dark:text-red-400">
            {{ addItemModalError }}
          </p>
          <div v-if="addItemModalLoading" class="py-6 text-center text-slate-500 dark:text-slate-400 text-sm">
            Loading menu items…
          </div>
          <div v-else class="max-h-[50vh] overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-200 dark:divide-slate-700">
            <div
              v-for="menuItem in addItemFilteredList"
              :key="(menuItem._addSource ?? 'restaurant') + '-' + menuItem.uuid"
              class="flex items-center gap-3 p-3 min-h-[44px]"
            >
              <div class="min-w-0 flex-1">
                <p class="font-medium text-charcoal dark:text-white truncate">{{ itemDisplayName(menuItem) }}</p>
                <p v-if="itemPriceDisplay(menuItem)" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ itemPriceDisplay(menuItem) }}</p>
              </div>
              <template v-if="isItemInCategory(menuItem)">
                <AppButton
                  variant="secondary"
                  size="sm"
                  class="min-h-[44px] shrink-0"
                  :disabled="addItemRemovingUuid === menuItem.uuid"
                  :aria-label="`Remove ${itemDisplayName(menuItem)} from category`"
                  :data-testid="`remove-item-from-category-${menuItem.uuid}`"
                  @click="removeItemFromCategory(menuItem)"
                >
                  <span v-if="addItemRemovingUuid === menuItem.uuid" class="material-icons animate-spin text-lg">sync</span>
                  <span v-else class="material-icons text-lg">remove_circle_outline</span>
                  <span class="ml-1">Remove from menu</span>
                </AppButton>
              </template>
              <AppButton
                v-else
                variant="primary"
                size="sm"
                class="min-h-[44px] min-w-[44px] shrink-0"
                :disabled="addItemAddingUuid === menuItem.uuid"
                :title="`Add ${itemDisplayName(menuItem)} to category`"
                :aria-label="`Add ${itemDisplayName(menuItem)} to category`"
                :data-testid="`add-item-to-category-${menuItem.uuid}`"
                @click="addItemToCategory(menuItem)"
              >
                <span v-if="addItemAddingUuid === menuItem.uuid" class="material-icons animate-spin text-lg">sync</span>
                <span v-else class="material-icons text-lg">add</span>
              </AppButton>
            </div>
            <p v-if="!addItemModalLoading && addItemFilteredList.length === 0" class="p-4 text-sm text-slate-500 dark:text-slate-400 text-center">
              No menu items, or none match the search.
            </p>
          </div>
        </div>
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" @click="closeAddItemModal">Done</AppButton>
        </template>
      </AppModal>

      <AvailabilityModal
        :open="availabilityModalOpen"
        :model-value="itemForAvailability ? (itemForAvailability.availability ?? null) : null"
        title="Item availability"
        :entity-name="itemForAvailability ? itemDisplayName(itemForAvailability) : ''"
        :api-save-error="availabilitySaveError"
        @save="saveItemAvailability"
        @close="closeAvailabilityModal"
      />

      <!-- Assign tags modal -->
      <AppModal
        :open="assignTagsModalOpen"
        :title="assignTagsModalItem ? `Assign tags to ${itemDisplayName(assignTagsModalItem)}` : 'Assign tags'"
        description="Select tags to show on this item on the public menu."
        @close="closeAssignTagsModal"
      >
        <div class="space-y-4">
          <p v-if="assignTagsModalError" role="alert" class="text-sm text-red-600 dark:text-red-400">
            {{ assignTagsModalError }}
          </p>
          <p v-if="assignTagsModalAvailable.length === 0 && !assignTagsModalLoading" class="text-sm text-slate-500 dark:text-slate-400">
            No tags available.
          </p>
          <div v-else-if="assignTagsModalAvailable.length" class="flex flex-wrap gap-2">
            <button
              v-for="tag in assignTagsModalAvailable"
              :key="tag.uuid"
              type="button"
              class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border min-h-[44px] transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
              :class="assignTagsSelectedUuids.includes(tag.uuid)
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600'"
              :title="tag.text"
              :aria-pressed="assignTagsSelectedUuids.includes(tag.uuid)"
              @click="toggleAssignTag(tag.uuid)"
            >
              <span
                v-if="tag.icon"
                class="material-icons text-lg shrink-0"
                :style="{ color: tag.color || undefined }"
              >
                {{ tag.icon }}
              </span>
              <span v-else class="material-icons text-lg shrink-0 text-slate-400">label</span>
              <span>{{ tag.text || 'Untitled' }}</span>
            </button>
          </div>
        </div>
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" @click="closeAssignTagsModal">Cancel</AppButton>
          <AppButton
            variant="primary"
            class="min-h-[44px]"
            :disabled="assignTagsModalSaving"
            @click="saveAssignTags"
          >
            <template v-if="assignTagsModalSaving" #icon>
              <span class="material-icons animate-spin">sync</span>
            </template>
            {{ assignTagsModalSaving ? 'Saving…' : 'Save' }}
          </AppButton>
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
import AvailabilityModal from '@/components/availability/AvailabilityModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import Restaurant from '@/models/Restaurant.js'
import MenuItem from '@/models/MenuItem.js'
import { formatCurrency } from '@/utils/format'
import { restaurantService, menuItemService, menuItemTagService, normalizeApiError } from '@/services'
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
const addItemModalLoading = ref(false)
const addItemModalError = ref('')
const addItemAddingUuid = ref(null)
const addItemRemovingUuid = ref(null)
/** UUID of menu item whose is_active is being toggled (loading state). */
const togglingItemUuid = ref(null)
/** UUID of menu item whose is_available is being toggled (loading state). */
const togglingAvailabilityUuid = ref(null)
/** UUID of tag currently hovered (for visible tooltip). */
const tagHoveredUuid = ref(null)
/** Assign tags modal */
const assignTagsModalOpen = ref(false)
const assignTagsModalItem = ref(null)
const assignTagsSelectedUuids = ref([])
const assignTagsModalAvailable = ref([])
const assignTagsModalLoading = ref(false)
const assignTagsModalSaving = ref(false)
const assignTagsModalError = ref('')
const availabilityModalOpen = ref(false)
const itemForAvailability = ref(null)
const savingAvailability = ref(false)
const availabilitySaveError = ref('')
/** Items for modal: restaurant items + catalog (standalone) items not already in this restaurant. */
const allMenuItemsForModal = ref([])
/** Catalog item uuids that have type with_variants (so restaurant items without source_variant_uuid cannot be added). */
const catalogUuidsWithVariants = ref(new Set())

/** Restaurant items already in this category (for sort_order when adding). */
const addItemInCategory = computed(() => {
  const cat = categoryUuid.value
  if (!cat) return []
  return allMenuItemsForModal.value.filter(
    (i) => i._addSource === 'restaurant' && (i.category_uuid ?? null) === cat
  )
})

/** Restaurant items not in this category (other categories or uncategorized). Exclude legacy items: catalog with_variants without source_variant_uuid. */
const addItemNotInCategory = computed(() => {
  const cat = categoryUuid.value
  if (!cat) return []
  const withVariants = catalogUuidsWithVariants.value
  return allMenuItemsForModal.value.filter((i) => {
    if (i._addSource !== 'restaurant' || (i.category_uuid ?? null) === cat) return false
    if ((i.source_variant_uuid ?? null) != null) return true
    if (!(i.source_menu_item_uuid ?? null)) return true
    return !withVariants.has(i.source_menu_item_uuid)
  })
})

/** Catalog (standalone) items not yet in this restaurant (deduped by source_menu_item_uuid). */
const addItemCatalogOnly = computed(() => {
  return allMenuItemsForModal.value.filter((i) => i._addSource === 'catalog')
})

/** All items for modal: addable (restaurant not in category + catalog), then in-category (Remove from menu). */
const addItemFullList = computed(() => {
  const notIn = addItemNotInCategory.value
  const catalog = addItemCatalogOnly.value
  const inCat = addItemInCategory.value
  return [...notIn, ...catalog, ...inCat]
})

/** Modal list filtered by search; each row shows Add or Remove from menu. */
const addItemFilteredList = computed(() => {
  const list = addItemFullList.value
  const q = (addItemSearchQuery.value ?? '').trim().toLowerCase()
  if (!q) return list
  return list.filter((item) => {
    const name = itemDisplayName(item)
    return name.toLowerCase().includes(q)
  })
})

function isItemInCategory(menuItem) {
  if (menuItem?._addSource === 'restaurant') {
    return (menuItem.category_uuid ?? null) === categoryUuid.value
  }
  if (menuItem?._addSource === 'catalog') {
    const sourceCatalogUuid = menuItem._sourceCatalogUuid ?? menuItem.uuid
    const sourceVariantUuid = menuItem._sourceVariantUuid ?? null
    const cat = categoryUuid.value
    return allMenuItemsForModal.value.some(
      (i) =>
        i._addSource === 'restaurant' &&
        (i.category_uuid ?? null) === cat &&
        (i.source_menu_item_uuid ?? null) === sourceCatalogUuid &&
        (i.source_variant_uuid ?? null) === sourceVariantUuid
    )
  }
  return false
}

function openAddItemModal() {
  addItemSearchQuery.value = ''
  addItemModalError.value = ''
  addItemAddingUuid.value = null
  addItemModalOpen.value = true
  fetchAllMenuItemsForModal()
}

function closeAddItemModal() {
  addItemModalOpen.value = false
  addItemModalError.value = ''
  addItemAddingUuid.value = null
  addItemRemovingUuid.value = null
}

async function fetchAllMenuItemsForModal() {
  if (!uuid.value) return
  addItemModalLoading.value = true
  addItemModalError.value = ''
  try {
    const [restaurantRes, userRes] = await Promise.all([
      restaurantService.listMenuItems(uuid.value),
      menuItemService.list(),
    ])
    const restaurantList = Array.isArray(restaurantRes?.data) ? restaurantRes.data : []
    const userList = Array.isArray(userRes?.data) ? userRes.data : []
    catalogUuidsWithVariants.value = new Set(
      userList
        .filter((raw) => (raw.restaurant_uuid ?? null) === null && (raw.type ?? '') === 'with_variants')
        .map((raw) => raw.uuid)
        .filter(Boolean)
    )
    const restaurantItems = restaurantList.map((i) => {
      const json = MenuItem.fromApi({ data: i }).toJSON()
      json._addSource = 'restaurant'
      return json
    })
    // Already in restaurant: simple/combo by source_menu_item_uuid; variants by (source_menu_item_uuid, source_variant_uuid)
    const sourceUuidsSimpleCombo = new Set(
      restaurantItems
        .filter((i) => !(i.source_variant_uuid ?? null))
        .map((i) => i.source_menu_item_uuid)
        .filter(Boolean)
    )
    const sourceVariantPairs = new Set(
      restaurantItems
        .filter((i) => (i.source_variant_uuid ?? null))
        .map((i) => `${i.source_menu_item_uuid}\0${i.source_variant_uuid}`)
    )
    const locale = defaultLocale.value
    const catalogItems = []
    for (const raw of userList) {
      if ((raw.restaurant_uuid ?? null) !== null) continue
      const catalogItem = MenuItem.fromApi({ data: raw })
      // With variants: only end variants are addable; never show the base item (e.g. "Burger")
      if (catalogItem.type === 'with_variants') {
        const skus = catalogItem.variant_skus ?? []
        if (skus.length === 0) continue
        const baseName = (catalogItem.translations?.[locale]?.name ?? catalogItem.translations?.en?.name ?? '').trim() || '—'
        const groupOrder = catalogItem.variantOptionGroupNames ?? []
        for (const sku of skus) {
          const skuUuid = sku.uuid ?? sku?.uuid
          if (!skuUuid) continue
          const pairKey = `${catalogItem.uuid}\0${skuUuid}`
          if (sourceVariantPairs.has(pairKey)) continue
          const variantLabel = typeof sku.displayLabel === 'function' ? sku.displayLabel(groupOrder) : (Object.values(sku.option_values || {}).filter(Boolean).join(', ') || '—')
          catalogItems.push({
            uuid: `catalog-${catalogItem.uuid}-${skuUuid}`,
            _addSource: 'catalog',
            _sourceCatalogUuid: catalogItem.uuid,
            _sourceVariantUuid: skuUuid,
            translations: { [locale]: { name: `${baseName} – ${variantLabel}` } },
            price: sku.price != null ? Number(sku.price) : null,
          })
        }
      } else {
        // Simple or combo: one row per item, exclude if already in restaurant
        if (sourceUuidsSimpleCombo.has(catalogItem.uuid)) continue
        const json = catalogItem.toJSON()
        json._addSource = 'catalog'
        catalogItems.push(json)
      }
    }
    allMenuItemsForModal.value = [...restaurantItems, ...catalogItems]
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
  const isCatalog = menuItem._addSource === 'catalog'
  try {
    if (isCatalog) {
      const sourceCatalogUuid = menuItem._sourceCatalogUuid ?? menuItem.uuid
      const payload = {
        source_menu_item_uuid: sourceCatalogUuid,
        category_uuid: categoryUuid.value,
        sort_order: nextSortOrder,
      }
      if (menuItem._sourceVariantUuid) {
        payload.source_variant_uuid = menuItem._sourceVariantUuid
      }
      await restaurantService.createMenuItem(uuid.value, payload)
    } else {
      await restaurantService.updateMenuItem(uuid.value, menuItem.uuid, {
        category_uuid: categoryUuid.value,
        sort_order: nextSortOrder,
      })
    }
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

async function removeItemFromCategory(menuItem) {
  if (!uuid.value || !categoryUuid.value || addItemRemovingUuid.value) return
  const restaurantItemUuid =
    menuItem._addSource === 'catalog'
      ? allMenuItemsForModal.value.find(
          (i) =>
            i._addSource === 'restaurant' &&
            (i.category_uuid ?? null) === categoryUuid.value &&
            (i.source_menu_item_uuid ?? null) === (menuItem._sourceCatalogUuid ?? menuItem.uuid) &&
            (i.source_variant_uuid ?? null) === (menuItem._sourceVariantUuid ?? null)
        )?.uuid
      : menuItem.uuid
  if (!restaurantItemUuid) return
  addItemRemovingUuid.value = menuItem.uuid
  addItemModalError.value = ''
  try {
    await restaurantService.updateMenuItem(uuid.value, restaurantItemUuid, {
      category_uuid: null,
      sort_order: 0,
    })
    restaurantService.invalidateMenuItemsCache(uuid.value)
    await loadItems()
    await fetchAllMenuItemsForModal()
    toastStore.success('Removed from category.')
  } catch (e) {
    addItemModalError.value = normalizeApiError(e).message
  } finally {
    addItemRemovingUuid.value = null
  }
}

function itemIsActive(item) {
  return item?.is_active !== false
}

function itemIsAvailable(item) {
  return item?.is_available !== false
}

async function toggleItemAvailability(item) {
  if (!uuid.value || !item?.uuid || togglingAvailabilityUuid.value) return
  const nextAvailable = !itemIsAvailable(item)
  togglingAvailabilityUuid.value = item.uuid
  try {
    await restaurantService.updateMenuItem(uuid.value, item.uuid, { is_available: nextAvailable })
    items.value = items.value.map((i) => (i.uuid === item.uuid ? { ...i, is_available: nextAvailable } : i))
    toastStore.success(nextAvailable ? 'Item marked available.' : 'Item marked not available.')
  } catch (e) {
    toastStore.error(normalizeApiError(e).message)
  } finally {
    togglingAvailabilityUuid.value = null
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
  const currency = restaurant.value?.currency ?? 'USD'
  return formatCurrency(n, currency)
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

async function toggleItemVisibility(item) {
  if (!uuid.value || !item?.uuid || togglingItemUuid.value) return
  const nextActive = !itemIsActive(item)
  togglingItemUuid.value = item.uuid
  try {
    await restaurantService.updateMenuItem(uuid.value, item.uuid, { is_active: nextActive })
    items.value = items.value.map((i) => (i.uuid === item.uuid ? { ...i, is_active: nextActive } : i))
    toastStore.success(nextActive ? 'Item visible on public menu.' : 'Item hidden from public menu.')
  } catch (e) {
    toastStore.error(normalizeApiError(e).message)
  } finally {
    togglingItemUuid.value = null
  }
}

function openAvailabilityModal(item) {
  itemForAvailability.value = item
  availabilityModalOpen.value = true
}

function closeAvailabilityModal() {
  availabilityModalOpen.value = false
  itemForAvailability.value = null
  availabilitySaveError.value = ''
}

async function saveItemAvailability(availability) {
  const item = itemForAvailability.value
  if (!uuid.value || !item?.uuid || savingAvailability.value) return
  savingAvailability.value = true
  availabilitySaveError.value = ''
  try {
    await restaurantService.updateMenuItem(uuid.value, item.uuid, { availability })
    items.value = items.value.map((i) => (i.uuid === item.uuid ? { ...i, availability } : i))
    toastStore.success('Availability updated.')
    closeAvailabilityModal()
  } catch (e) {
    availabilitySaveError.value = normalizeApiError(e).message
  } finally {
    savingAvailability.value = false
  }
}

function openAssignTagsModal(item) {
  if (!item?.uuid) return
  assignTagsModalItem.value = item
  assignTagsSelectedUuids.value = Array.isArray(item.tags) ? item.tags.map((t) => t.uuid).filter(Boolean) : []
  assignTagsModalError.value = ''
  assignTagsModalAvailable.value = []
  assignTagsModalOpen.value = true
  assignTagsModalLoading.value = true
  menuItemTagService
    .list()
    .then((list) => {
      assignTagsModalAvailable.value = Array.isArray(list) ? list.map((t) => ({ uuid: t.uuid, color: t.color, icon: t.icon, text: t.text })) : []
    })
    .catch(() => {
      assignTagsModalAvailable.value = []
    })
    .finally(() => {
      assignTagsModalLoading.value = false
    })
}

function closeAssignTagsModal() {
  assignTagsModalOpen.value = false
  assignTagsModalItem.value = null
  assignTagsSelectedUuids.value = []
  assignTagsModalAvailable.value = []
  assignTagsModalError.value = ''
}

function toggleAssignTag(tagUuid) {
  const list = [...assignTagsSelectedUuids.value]
  const idx = list.indexOf(tagUuid)
  if (idx === -1) list.push(tagUuid)
  else list.splice(idx, 1)
  assignTagsSelectedUuids.value = list
}

async function saveAssignTags() {
  const item = assignTagsModalItem.value
  if (!uuid.value || !item?.uuid || assignTagsModalSaving.value) return
  assignTagsModalSaving.value = true
  assignTagsModalError.value = ''
  try {
    await restaurantService.updateMenuItem(uuid.value, item.uuid, { tag_uuids: [...assignTagsSelectedUuids.value] })
    const newTags = assignTagsModalAvailable.value.filter((t) => assignTagsSelectedUuids.value.includes(t.uuid))
    items.value = items.value.map((i) => (i.uuid === item.uuid ? { ...i, tags: newTags } : i))
    toastStore.success('Tags updated.')
    closeAssignTagsModal()
  } catch (e) {
    assignTagsModalError.value = normalizeApiError(e).message
  } finally {
    assignTagsModalSaving.value = false
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
