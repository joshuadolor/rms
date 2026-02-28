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
      <p class="text-slate-500 dark:text-slate-400 mb-4">{{ $t('app.restaurantNotFound') }}</p>
      <AppBackLink to="/app/restaurants" />
    </div>

    <template v-else>
      <header class="mb-4 lg:mb-6">
        <div>
          <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Menu management</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Menus, categories and items. Drag to reorder.</p>
        </div>

        <!-- Menu selector: create first or pick menu -->
        <div class="mt-4 space-y-2">
          <label class="block text-sm font-semibold text-charcoal dark:text-white">Menu</label>
          <div v-if="menusLoading" class="h-12 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse w-full max-w-xs" />
          <template v-else-if="!menus.length">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-2">Create your first menu to add categories and items.</p>
            <AppButton variant="primary" class="min-h-[44px]" :disabled="creatingMenu" @click="createFirstMenu">
              <template v-if="creatingMenu" #icon><span class="material-icons animate-spin text-lg">sync</span></template>
              {{ creatingMenu ? 'Creating…' : 'Create menu' }}
            </AppButton>
          </template>
          <div v-else class="flex flex-col sm:flex-row sm:items-center gap-2">
            <select
              v-model="selectedMenuUuid"
              class="min-h-[44px] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-primary focus:outline-none w-full max-w-xs"
              aria-label="Select menu"
            >
              <option v-for="m in menus" :key="m.uuid" :value="m.uuid">
                {{ m.name || 'Unnamed menu' }} {{ m.is_active ? '' : '(inactive)' }}
              </option>
            </select>
            <div class="flex items-center gap-2">
              <button
                v-if="selectedMenu"
                type="button"
                class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-xl border transition-colors"
                :class="selectedMenu.is_active ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-zinc-800 border-slate-200 dark:border-slate-700 text-slate-500'"
                :title="selectedMenu.is_active ? 'Hide menu on public site' : 'Show menu on public site'"
                :aria-label="(selectedMenu.is_active ? 'Hide' : 'Show') + ' ' + (selectedMenu.name || 'menu') + ' on public site'"
                @click="toggleMenuActive(selectedMenu)"
              >
                <span class="material-icons text-xl">{{ selectedMenu.is_active ? 'visibility' : 'visibility_off' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Tabs: Categories | Items -->
        <div v-if="menus.length" class="mt-4 flex p-1 bg-slate-100 dark:bg-zinc-800 rounded-lg max-w-md">
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
      <div v-show="menus.length && activeTab === 'categories'" class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-bold text-charcoal dark:text-white">Categories</h3>
          <span class="text-xs font-medium text-slate-500 bg-slate-100 dark:bg-zinc-700 px-2 py-1 rounded-full">{{ categories.length }} total</span>
        </div>
        <div v-if="categoriesLoading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-16 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
        </div>
        <div v-else-if="!categories.length" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center">
          <p class="text-slate-500 dark:text-slate-400 mb-4">No categories yet. Use the + button below to add one.</p>
        </div>
        <draggable
          v-else
          v-model="orderedCategories"
          item-key="uuid"
          handle=".drag-handle"
          class="space-y-2"
          @end="onReorderCategories"
        >
          <template #item="{ element: cat }">
            <li class="flex items-center gap-3 p-4 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 shadow-sm list-none">
              <button
                type="button"
                class="drag-handle p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 min-h-[44px] min-w-[44px] flex items-center justify-center"
                title="Drag to reorder"
                aria-label="Drag to reorder"
              >
                <span class="material-icons">drag_indicator</span>
              </button>
              <div class="min-w-0 flex-1">
                <p class="font-semibold text-charcoal dark:text-white">{{ categoryName(cat) }}</p>
              </div>
              <AppButton variant="ghost" size="sm" class="min-h-[44px]" title="Edit category" aria-label="Edit category" @click="openCategoryModal(cat)">
                <span class="material-icons">edit</span>
              </AppButton>
            </li>
          </template>
        </draggable>
      </div>

      <!-- Items tab -->
      <div v-show="menus.length && activeTab === 'items'" class="space-y-6">
        <div v-if="listLoading" class="space-y-3">
          <div v-for="i in 3" :key="i" class="h-20 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
        </div>
        <div v-else-if="!menuItems.length" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center">
          <p class="text-slate-500 dark:text-slate-400 mb-4">No menu items yet. Use the + button below to add one.</p>
        </div>
        <div v-else class="space-y-6">
          <section
            v-for="group in itemsByCategory"
            :key="group.categoryUuid ?? 'uncategorized'"
            class="space-y-2"
          >
            <h4 class="text-sm font-semibold text-slate-600 dark:text-slate-300">
              {{ group.categoryName }}
            </h4>
            <draggable
              v-model="group.items"
              item-key="uuid"
              handle=".drag-handle"
              class="space-y-2"
              @end="() => onReorderItems(group)"
            >
              <template #item="{ element: item }">
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 min-h-[44px]">
                  <button
                    type="button"
                    class="drag-handle p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 min-h-[44px] min-w-[44px] flex items-center justify-center"
                    title="Drag to reorder"
                    aria-label="Drag to reorder"
                  >
                    <span class="material-icons">drag_indicator</span>
                  </button>
                  <div class="min-w-0 flex-1">
                    <p class="font-medium text-charcoal dark:text-white truncate">{{ itemName(item) }}</p>
                    <p v-if="itemDescription(item)" class="text-sm text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ itemDescription(item) }}</p>
                  </div>
                  <router-link :to="{ name: 'RestaurantMenuItemEdit', params: { uuid, itemUuid: item.uuid } }" class="shrink-0" title="Edit menu item" aria-label="Edit menu item">
                    <AppButton variant="ghost" size="sm" class="min-h-[44px]">
                      <span class="material-icons">edit</span>
                    </AppButton>
                  </router-link>
                </div>
              </template>
            </draggable>
          </section>
        </div>
      </div>

      <!-- Category modal: create / edit -->
      <AppModal
        :open="categoryModalOpen"
        :title="editingCategory ? 'Edit category' : 'Add category'"
        :description="editingCategory ? 'Update category name.' : 'Create a new menu category (e.g. Starters, Mains).'"
        @close="closeCategoryModal"
      >
        <form class="space-y-4" novalidate @submit.prevent="saveCategory">
          <AppInput
            v-model="categoryForm.name"
            label="Category name"
            type="text"
            placeholder="e.g. Starters, Main courses"
            :error="categoryFormError"
          />
        </form>
        <template #footer>
          <AppButton variant="secondary" class="min-h-[44px]" @click="closeCategoryModal">Cancel</AppButton>
          <AppButton variant="primary" class="min-h-[44px]" :disabled="savingCategory" @click="saveCategory">
            {{ savingCategory ? 'Saving…' : 'Save' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- FAB: Add category only (menu item creation is in Menu items page) -->
      <button
        v-if="selectedMenuUuid && activeTab === 'categories'"
        type="button"
        class="fixed right-6 w-14 h-14 bg-primary text-white rounded-full shadow-lg flex items-center justify-center transition-transform hover:scale-105 active:scale-95 z-20 min-h-[56px] min-w-[56px] bottom-[calc(5.5rem+env(safe-area-inset-bottom,0px))] lg:bottom-6"
        title="Add category"
        aria-label="Add category"
        @click="openCategoryModal()"
      >
        <span class="material-icons text-3xl">add</span>
      </button>
      <div class="h-20" aria-hidden="true" />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import AppBackLink from '@/components/AppBackLink.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import AppButton from '@/components/ui/AppButton.vue'
import { getLocaleDisplay } from '@/config/locales'
import Restaurant from '@/models/Restaurant.js'
import Menu from '@/models/Menu.js'
import Category from '@/models/Category.js'
import MenuItem from '@/models/MenuItem.js'
import { restaurantService, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const breadcrumbStore = useBreadcrumbStore()
const toastStore = useToastStore()
const uuid = computed(() => route.params.uuid)

const activeTab = ref('items')
const loading = ref(true)
const listLoading = ref(true)
const menusLoading = ref(true)
const categoriesLoading = ref(false)
const restaurant = ref(null)
const menus = ref([])
const selectedMenuUuid = ref(null)
const categories = ref([])
const menuItems = ref([])
const error = ref('')
const creatingMenu = ref(false)

const categoryModalOpen = ref(false)
const editingCategory = ref(null)
const categoryForm = ref({ name: '' })
const categoryFormError = ref('')
const savingCategory = ref(false)

const defaultLocale = computed(() => restaurant.value?.default_locale ?? 'en')

/** Currently selected menu (for the dropdown); used for the single visibility toggle. */
const selectedMenu = computed(() =>
  menus.value.find((m) => m.uuid === selectedMenuUuid.value) ?? null
)

const orderedCategories = computed({
  get: () => categories.value,
  set: (v) => { categories.value = v },
})

function categoryName(cat) {
  if (!cat) return '—'
  const t = cat.translations?.[defaultLocale.value]
  return t?.name ?? '—'
}

const itemsByCategory = computed(() => {
  const def = defaultLocale.value
  const catMap = new Map(categories.value.map((c) => [c.uuid, categoryName(c)]))
  catMap.set(null, 'Uncategorized')
  const groups = []
  const seen = new Set()
  const addGroup = (categoryUuid) => {
    if (seen.has(categoryUuid ?? 'null')) return
    seen.add(categoryUuid ?? 'null')
    const items = menuItems.value.filter((i) => (i.category_uuid ?? null) === (categoryUuid ?? null))
    if (items.length) {
      groups.push({
        categoryUuid: categoryUuid ?? null,
        categoryName: catMap.get(categoryUuid ?? null) ?? 'Uncategorized',
        items,
      })
    }
  }
  menuItems.value.forEach((i) => addGroup(i.category_uuid ?? null))
  return groups
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
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else error.value = normalizeApiError(e).message
  } finally {
    loading.value = false
  }
}

async function loadMenus() {
  if (!uuid.value) return
  menusLoading.value = true
  try {
    const res = await restaurantService.listMenus(uuid.value)
    const list = (res.data ?? []).map((m) => Menu.fromApi({ data: m }).toJSON())
    menus.value = list
    if (list.length && !selectedMenuUuid.value) selectedMenuUuid.value = list[0].uuid
  } catch (e) {
    error.value = normalizeApiError(e).message
    menus.value = []
  } finally {
    menusLoading.value = false
  }
}

async function loadCategories() {
  if (!uuid.value || !selectedMenuUuid.value) return
  categoriesLoading.value = true
  try {
    const res = await restaurantService.listCategories(uuid.value, selectedMenuUuid.value)
    categories.value = (res.data ?? []).map((c) => Category.fromApi({ data: c }).toJSON())
  } catch (e) {
    error.value = normalizeApiError(e).message
    categories.value = []
  } finally {
    categoriesLoading.value = false
  }
}

async function loadMenuItems() {
  if (!uuid.value) return
  listLoading.value = true
  try {
    const res = await restaurantService.listMenuItems(uuid.value)
    const list = Array.isArray(res?.data) ? res.data : []
    menuItems.value = list.map((i) => MenuItem.fromApi({ data: i }).toJSON())
  } catch (e) {
    error.value = normalizeApiError(e).message
    menuItems.value = []
  } finally {
    listLoading.value = false
  }
}

async function createFirstMenu() {
  if (!uuid.value || creatingMenu.value) return
  creatingMenu.value = true
  error.value = ''
  try {
    const res = await restaurantService.createMenu(uuid.value, { name: 'Main menu', is_active: true })
    const menu = Menu.fromApi(res).toJSON()
    menus.value = [menu]
    selectedMenuUuid.value = menu.uuid
    toastStore.success('Menu created.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    creatingMenu.value = false
  }
}

async function toggleMenuActive(m) {
  if (!uuid.value || !m.uuid) return
  try {
    await restaurantService.updateMenu(uuid.value, m.uuid, { is_active: !m.is_active })
    const list = menus.value.map((x) => (x.uuid === m.uuid ? { ...x, is_active: !x.is_active } : x))
    menus.value = list
    toastStore.success(m.is_active ? 'Menu hidden from public site.' : 'Menu visible on public site.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  }
}

function openCategoryModal(category = null) {
  editingCategory.value = category ?? null
  const name = category?.translations?.[defaultLocale.value]?.name ?? ''
  categoryForm.value = { name }
  categoryFormError.value = ''
  categoryModalOpen.value = true
}

function closeCategoryModal() {
  categoryModalOpen.value = false
  editingCategory.value = null
  categoryForm.value = { name: '' }
  categoryFormError.value = ''
}

async function saveCategory() {
  const name = categoryForm.value.name?.trim()
  if (!name) {
    categoryFormError.value = 'Category name is required.'
    return
  }
  categoryFormError.value = ''
  savingCategory.value = true
  const translations = { [defaultLocale.value]: { name } }
  try {
    if (editingCategory.value) {
      await restaurantService.updateCategory(uuid.value, selectedMenuUuid.value, editingCategory.value.uuid, { translations })
      toastStore.success('Category updated.')
    } else {
      await restaurantService.createCategory(uuid.value, selectedMenuUuid.value, { translations })
      toastStore.success('Category created.')
    }
    closeCategoryModal()
    await loadCategories()
  } catch (e) {
    categoryFormError.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    savingCategory.value = false
  }
}

async function onReorderCategories() {
  const order = categories.value.map((c) => c.uuid)
  try {
    await restaurantService.reorderCategories(uuid.value, selectedMenuUuid.value, order)
    toastStore.success('Order updated.')
  } catch (e) {
    error.value = normalizeApiError(e).message
    await loadCategories()
  }
}

async function onReorderItems(group) {
  const order = group.items.map((i) => i.uuid)
  const categoryUuid = group.categoryUuid
  if (categoryUuid == null) {
    error.value = 'Reordering uncategorized items is not supported. Assign a category first.'
    await loadMenuItems()
    return
  }
  try {
    await restaurantService.reorderMenuItems(uuid.value, categoryUuid, order)
    toastStore.success('Order updated.')
  } catch (e) {
    error.value = normalizeApiError(e).message
    await loadMenuItems()
  }
}

onMounted(() => {
  loadRestaurant().then(() => {
    if (restaurant.value) {
      loadMenus().then(() => {
        if (selectedMenuUuid.value) loadCategories()
        loadMenuItems()
      })
    }
  })
})

watch(uuid, () => {
  loading.value = true
  selectedMenuUuid.value = null
  loadRestaurant().then(() => {
    if (restaurant.value) {
      loadMenus().then(() => {
        if (selectedMenuUuid.value) loadCategories()
        loadMenuItems()
      })
    }
  })
})

watch(selectedMenuUuid, (val) => {
  if (val) loadCategories()
})
</script>
