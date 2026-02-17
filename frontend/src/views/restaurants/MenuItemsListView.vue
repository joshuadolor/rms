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
      <AppBackLink to="/app/restaurants" />
    </div>

    <template v-else>
      <header class="mb-4 lg:mb-6">
        <div>
            <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Menu management</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Categories and items. Drag items to reorder.</p>
          </div>
        <!-- Tabs: Categories | Items (inspired by stitch menu_management) -->
        <div class="mt-4 flex p-1 bg-slate-100 dark:bg-zinc-800 rounded-lg max-w-md">
          <button
            type="button"
            class="flex-1 py-2 text-sm font-semibold rounded-md transition-all min-h-[44px]"
            :class="activeTab === 'categories' ? 'bg-white dark:bg-primary text-primary dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'"
            @click="activeTab = 'categories'"
          >
            Categories
          </button>
          <button
            type="button"
            class="flex-1 py-2 text-sm font-semibold rounded-md transition-all min-h-[44px]"
            :class="activeTab === 'items' ? 'bg-white dark:bg-primary text-primary dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'"
            @click="activeTab = 'items'"
          >
            Items
          </button>
        </div>
      </header>

      <div
        v-if="error"
        role="alert"
        class="mb-6 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
      >
        {{ error }}
      </div>

      <!-- Categories tab -->
      <div v-show="activeTab === 'categories'" class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-bold text-charcoal dark:text-white">Categories</h3>
          <span class="text-xs font-medium text-slate-500 bg-slate-100 dark:bg-zinc-700 px-2 py-1 rounded-full">{{ categories.length }} total</span>
        </div>
        <div v-if="!categories.length" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center">
          <p class="text-slate-500 dark:text-slate-400 mb-4">No categories yet. Use the + button below to add one.</p>
        </div>
        <ul v-else class="space-y-2">
          <li
            v-for="cat in categories"
            :key="cat.id"
            class="flex items-center gap-3 p-4 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 shadow-sm"
          >
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-charcoal dark:text-white">{{ cat.name }}</p>
              <p v-if="cat.description" class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ cat.description }}</p>
            </div>
            <AppButton variant="ghost" size="sm" class="min-h-[44px]" @click="openCategoryModal(cat)">
              <span class="material-icons">edit</span>
            </AppButton>
          </li>
        </ul>
      </div>

      <!-- Items tab -->
      <div v-show="activeTab === 'items'">
      <div v-if="listLoading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-20 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      </div>

      <div v-else-if="!menuItems.length" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center">
        <p class="text-slate-500 dark:text-slate-400 mb-4">No menu items yet. Use the + button below to add one.</p>
      </div>

      <div v-else class="space-y-2">
        <draggable
          v-model="orderedItems"
          item-key="uuid"
          handle=".drag-handle"
          @end="onReorder"
        >
          <template #item="{ element: item }">
            <div class="flex items-center gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 min-h-[44px]">
              <button
                type="button"
                class="drag-handle p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 min-h-[44px] min-w-[44px] flex items-center justify-center"
                aria-label="Drag to reorder"
              >
                <span class="material-icons">drag_indicator</span>
              </button>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-charcoal dark:text-white truncate">
                  {{ itemName(item) }}
                </p>
                <p v-if="itemDescription(item)" class="text-sm text-slate-500 dark:text-slate-400 truncate mt-0.5">
                  {{ itemDescription(item) }}
                </p>
              </div>
              <router-link :to="{ name: 'RestaurantMenuItemEdit', params: { uuid, itemUuid: item.uuid } }" class="shrink-0">
                <AppButton variant="ghost" size="sm" class="min-h-[44px]">
                  <span class="material-icons">edit</span>
                </AppButton>
              </router-link>
            </div>
          </template>
        </draggable>
      </div>
      </div>

      <!-- Category modal: create / edit -->
      <AppModal
        :open="categoryModalOpen"
        :title="editingCategory ? 'Edit category' : 'Add category'"
        :description="editingCategory ? 'Update category name and description.' : 'Create a new menu category.'"
        @close="closeCategoryModal"
      >
        <form class="space-y-4" novalidate @submit.prevent="saveCategory">
          <AppInput
            v-model="categoryForm.name"
            label="Category name"
            type="text"
            placeholder="e.g. Starters, Main courses"
            required
            :error="categoryFormError"
          />
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Description (optional)</label>
            <textarea
              v-model="categoryForm.description"
              rows="2"
              placeholder="Short description for this category"
              class="w-full rounded-xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-3 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none"
            />
          </div>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeCategoryModal">Cancel</AppButton>
          <AppButton variant="primary" :disabled="savingCategory" @click="saveCategory">
            {{ savingCategory ? 'Saving…' : 'Save' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- Add menu item modal -->
      <AppModal
        :open="itemModalOpen"
        title="Add menu item"
        description="Add a new item to the menu. Name is required for the default language."
        @close="closeItemModal"
      >
        <form class="space-y-4" novalidate @submit.prevent="saveMenuItem">
          <div
            v-if="itemFormError"
            role="alert"
            class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
          >
            {{ itemFormError }}
          </div>
          <AppInput
            v-model="itemForm.name"
            :label="`Name (${getLocaleDisplay(defaultLocale)})`"
            type="text"
            placeholder="e.g. Margherita Pizza"
            :error="itemFormFieldError"
          />
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Description (optional)</label>
            <textarea
              v-model="itemForm.description"
              rows="3"
              placeholder="Short description"
              class="w-full rounded-xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-3 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none"
            />
          </div>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeItemModal">Cancel</AppButton>
          <AppButton variant="primary" :disabled="savingItem" @click="saveMenuItem">
            <template v-if="savingItem" #icon>
              <span class="material-icons animate-spin text-lg">sync</span>
            </template>
            {{ savingItem ? 'Saving…' : 'Save' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- Floating Action Button: above bottom tabs on mobile, bottom-right on desktop -->
      <button
        type="button"
        class="fixed right-6 w-14 h-14 bg-primary text-white rounded-full shadow-lg flex items-center justify-center transition-transform hover:scale-105 active:scale-95 z-20 min-h-[56px] min-w-[56px] bottom-[calc(5.5rem+env(safe-area-inset-bottom,0px))] lg:bottom-6"
        :aria-label="activeTab === 'categories' ? 'Add category' : 'Add menu item'"
        @click="onFabClick"
      >
        <span class="material-icons text-3xl">add</span>
      </button>
      <!-- Spacer so content isn't hidden behind FAB -->
      <div class="h-20" aria-hidden="true" />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import AppBackLink from '@/components/AppBackLink.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import AppButton from '@/components/ui/AppButton.vue'
import { getLocaleDisplay } from '@/config/locales'
import { restaurantService, normalizeApiError, getValidationErrors } from '@/services'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const breadcrumbStore = useBreadcrumbStore()
const toastStore = useToastStore()
const uuid = computed(() => route.params.uuid)

const activeTab = ref('items')
const loading = ref(true)
const listLoading = ref(true)
const restaurant = ref(null)
const menuItems = ref([])
const error = ref('')

// Menu categories (local state; API can be wired later)
const categories = ref([])
const categoryModalOpen = ref(false)
const editingCategory = ref(null)
const categoryForm = ref({ name: '', description: '' })
const categoryFormError = ref('')
const savingCategory = ref(false)

// Add menu item modal (no redirect)
const itemModalOpen = ref(false)
const itemForm = ref({ name: '', description: '' })
const itemFormError = ref('')
const itemFormFieldError = ref('')
const savingItem = ref(false)

function openCategoryModal(category = null) {
  editingCategory.value = category ?? null
  categoryForm.value = {
    name: category?.name ?? '',
    description: category?.description ?? '',
  }
  categoryFormError.value = ''
  categoryModalOpen.value = true
}

function closeCategoryModal() {
  categoryModalOpen.value = false
  editingCategory.value = null
  categoryForm.value = { name: '', description: '' }
  categoryFormError.value = ''
}

function onFabClick() {
  if (activeTab.value === 'categories') {
    openCategoryModal()
  } else {
    openItemModal()
  }
}

function saveCategory() {
  const name = categoryForm.value.name?.trim()
  if (!name) {
    categoryFormError.value = 'Category name is required.'
    return
  }
  categoryFormError.value = ''
  savingCategory.value = true
  if (editingCategory.value) {
    const idx = categories.value.findIndex((c) => c.id === editingCategory.value.id)
    if (idx !== -1) {
      categories.value = categories.value.map((c) =>
        c.id === editingCategory.value.id
          ? { ...c, name, description: categoryForm.value.description?.trim() || '' }
          : c
      )
    }
  } else {
    categories.value = [
      ...categories.value,
      {
        id: typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `cat-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
        name,
        description: categoryForm.value.description?.trim() || '',
      },
    ]
  }
  savingCategory.value = false
  closeCategoryModal()
}

function openItemModal() {
  itemForm.value = { name: '', description: '' }
  itemFormError.value = ''
  itemFormFieldError.value = ''
  itemModalOpen.value = true
}

function closeItemModal() {
  itemModalOpen.value = false
  itemForm.value = { name: '', description: '' }
  itemFormError.value = ''
  itemFormFieldError.value = ''
}

async function saveMenuItem() {
  const name = (itemForm.value.name ?? '').trim()
  itemFormError.value = ''
  itemFormFieldError.value = ''
  if (!name) {
    itemFormFieldError.value = 'Name is required.'
    return
  }
  const defLoc = restaurant.value?.default_locale ?? 'en'
  const locs = restaurant.value?.languages ?? [defLoc]
  const translations = {}
  for (const loc of locs) {
    translations[loc] = {
      name: loc === defLoc ? name : '',
      description: loc === defLoc ? (itemForm.value.description?.trim() || null) : null,
    }
  }
  const sortOrder = menuItems.value.length
  savingItem.value = true
  try {
    await restaurantService.createMenuItem(uuid.value, { sort_order: sortOrder, translations })
    toastStore.success('Menu item created.')
    closeItemModal()
    await loadMenuItems()
  } catch (e) {
    const errs = getValidationErrors(e)
    itemFormError.value = e?.response?.data?.message ?? normalizeApiError(e).message
    if (errs['translations.' + defLoc + '.name']) itemFormFieldError.value = errs['translations.' + defLoc + '.name']
  } finally {
    savingItem.value = false
  }
}

const defaultLocale = computed(() => restaurant.value?.default_locale ?? 'en')

const orderedItems = computed({
  get: () => menuItems.value,
  set: (v) => { menuItems.value = v },
})

function itemName(item) {
  const t = item?.translations?.[defaultLocale.value]
  return t?.name ?? '—'
}

function itemDescription(item) {
  const t = item?.translations?.[defaultLocale.value]
  return t?.description ?? ''
}

async function loadRestaurant() {
  if (!uuid.value) return
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res.data ?? null
    breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else error.value = normalizeApiError(e).message
  } finally {
    loading.value = false
  }
}

async function loadMenuItems() {
  if (!uuid.value) return
  listLoading.value = true
  error.value = ''
  try {
    const res = await restaurantService.listMenuItems(uuid.value)
    menuItems.value = res.data ?? []
  } catch (e) {
    error.value = normalizeApiError(e).message
    menuItems.value = []
  } finally {
    listLoading.value = false
  }
}

async function onReorder() {
  error.value = ''
  const updates = menuItems.value.map((item, index) =>
    restaurantService.updateMenuItem(uuid.value, item.uuid, { sort_order: index })
  )
  try {
    await Promise.all(updates)
  } catch (e) {
    error.value = normalizeApiError(e).message
    await loadMenuItems()
  }
}

onMounted(() => {
  loadRestaurant().then(() => {
    if (restaurant.value) loadMenuItems()
  })
})

watch(uuid, () => {
  loading.value = true
  loadRestaurant().then(() => {
    if (restaurant.value) loadMenuItems()
  })
})
</script>
