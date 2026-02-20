<template>
  <div class="max-w-3xl">
    <header class="mb-6 lg:mb-8">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Add menu item</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Choose type, then add name and type-specific details.</p>
    </header>

    <form class="space-y-6" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="error"
        role="alert"
        aria-live="polite"
        class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
      >
        {{ error }}
      </div>

      <!-- Type selector -->
      <section class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6">
        <h3 class="font-semibold text-charcoal dark:text-white mb-3">Type</h3>
        <div class="flex flex-col gap-2" role="radiogroup" aria-label="Menu item type">
          <label class="flex items-center gap-3 min-h-[44px] cursor-pointer rounded-lg border border-slate-200 dark:border-slate-700 p-3 has-[:checked]:ring-2 has-[:checked]:ring-primary has-[:checked]:border-primary">
            <input v-model="form.type" type="radio" value="simple" class="w-5 h-5" data-testid="type-simple" />
            <span class="font-medium text-charcoal dark:text-white">Simple</span>
            <span class="text-sm text-slate-500 dark:text-slate-400">Single item with one price</span>
          </label>
          <label class="flex items-center gap-3 min-h-[44px] cursor-pointer rounded-lg border border-slate-200 dark:border-slate-700 p-3 has-[:checked]:ring-2 has-[:checked]:ring-primary has-[:checked]:border-primary">
            <input v-model="form.type" type="radio" value="combo" class="w-5 h-5" data-testid="type-combo" />
            <span class="font-medium text-charcoal dark:text-white">Combo</span>
            <span class="text-sm text-slate-500 dark:text-slate-400">Bundle of other menu items</span>
          </label>
          <label class="flex items-center gap-3 min-h-[44px] cursor-pointer rounded-lg border border-slate-200 dark:border-slate-700 p-3 has-[:checked]:ring-2 has-[:checked]:ring-primary has-[:checked]:border-primary">
            <input v-model="form.type" type="radio" value="with_variants" class="w-5 h-5" data-testid="type-with_variants" />
            <span class="font-medium text-charcoal dark:text-white">With variants</span>
            <span class="text-sm text-slate-500 dark:text-slate-400">Options (e.g. size, type) with price per combination</span>
          </label>
        </div>
      </section>

      <!-- Price: simple (one field) or combo (combo price) - before Name & description -->
      <section v-if="form.type === 'simple'" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6">
        <AppInput
          v-model="form.price"
          label="Price (optional)"
          type="number"
          min="0"
          step="0.01"
          placeholder="e.g. 10.00"
          :error="fieldErrors.price"
        />
      </section>
      <section v-else-if="form.type === 'combo'" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4">
        <AppInput
          v-model="form.combo_price"
          label="Combo price (optional)"
          type="number"
          min="0"
          step="0.01"
          placeholder="e.g. 12.00"
          :error="fieldErrors.combo_price"
        />
      </section>

      <!-- Name & description (all types) -->
      <section class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4">
        <AppInput
          v-model="form.name"
          label="Name"
          type="text"
          placeholder="e.g. Margherita Pizza"
          :error="fieldErrors.name"
        />
        <div>
          <label for="desc" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Description (optional)</label>
          <textarea
            id="desc"
            v-model="form.description"
            rows="3"
            class="w-full rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-y min-h-[44px]"
            placeholder="Short description"
          />
        </div>
      </section>

      <!-- Combo entries (after Name & description in create) -->
      <template v-if="form.type === 'combo'">
        <section class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="font-semibold text-charcoal dark:text-white">Combo entries</h3>
            <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px]" @click="addComboEntry">
              <template #icon><span class="material-icons">add</span></template>
              Add entry
            </AppButton>
          </div>
          <p v-if="fieldErrors.combo_entries" class="text-sm text-red-600 dark:text-red-400">{{ fieldErrors.combo_entries }}</p>
          <ul class="space-y-4">
            <li
              v-for="(entry, idx) in form.combo_entries"
              :key="idx"
              class="flex flex-col gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700"
            >
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Entry {{ idx + 1 }}</span>
                <AppButton
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="min-h-[44px] min-w-[44px] text-red-600 dark:text-red-400"
                  aria-label="Remove entry"
                  @click="removeComboEntry(idx)"
                >
                  <span class="material-icons">remove_circle_outline</span>
                </AppButton>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label :for="`combo-item-${idx}`" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Menu item</label>
                  <select
                    :id="`combo-item-${idx}`"
                    v-model="entry.menu_item_uuid"
                    class="w-full min-h-[44px] rounded-lg border px-4 py-2 bg-white dark:bg-zinc-800 text-charcoal dark:text-white transition-colors"
                    :class="fieldErrors[`combo_entries.${idx}.menu_item_uuid`] ? 'border-red-500 dark:border-red-400 ring-2 ring-red-500/20' : 'border-slate-200 dark:border-slate-700'"
                    aria-label="Select menu item"
                    :aria-invalid="!!fieldErrors[`combo_entries.${idx}.menu_item_uuid`]"
                    :aria-describedby="fieldErrors[`combo_entries.${idx}.menu_item_uuid`] ? `combo-item-${idx}-error` : undefined"
                  >
                    <option value="">— Select item —</option>
                    <option
                      v-for="catItem in catalogItems"
                      :key="catItem.uuid"
                      :value="catItem.uuid"
                    >
                      {{ catalogItemName(catItem) }}
                    </option>
                  </select>
                  <p
                    v-if="fieldErrors[`combo_entries.${idx}.menu_item_uuid`]"
                    :id="`combo-item-${idx}-error`"
                    class="text-sm text-red-600 dark:text-red-400 mt-1"
                    role="alert"
                  >
                    {{ fieldErrors[`combo_entries.${idx}.menu_item_uuid`] }}
                  </p>
                </div>
                <div v-if="selectedCatalogItemHasVariants(entry.menu_item_uuid)">
                  <label :for="`combo-variant-${idx}`" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Variant</label>
                  <select
                    :id="`combo-variant-${idx}`"
                    v-model="entry.variant_uuid"
                    class="w-full min-h-[44px] rounded-lg border px-4 py-2 bg-white dark:bg-zinc-800 text-charcoal dark:text-white transition-colors"
                    :class="fieldErrors[`combo_entries.${idx}.variant_uuid`] ? 'border-red-500 dark:border-red-400 ring-2 ring-red-500/20' : 'border-slate-200 dark:border-slate-700'"
                    aria-label="Select variant"
                    :aria-invalid="!!fieldErrors[`combo_entries.${idx}.variant_uuid`]"
                    :aria-describedby="fieldErrors[`combo_entries.${idx}.variant_uuid`] ? `combo-variant-${idx}-error` : undefined"
                  >
                    <option :value="null">— Select variant —</option>
                    <option
                      v-for="sku in variantSkusForItem(entry.menu_item_uuid)"
                      :key="sku.uuid"
                      :value="sku.uuid"
                    >
                      {{ sku.displayLabel ? sku.displayLabel() : optionValuesLabel(sku.option_values) }}
                    </option>
                  </select>
                  <p
                    v-if="fieldErrors[`combo_entries.${idx}.variant_uuid`]"
                    :id="`combo-variant-${idx}-error`"
                    class="text-sm text-red-600 dark:text-red-400 mt-1"
                    role="alert"
                  >
                    {{ fieldErrors[`combo_entries.${idx}.variant_uuid`] }}
                  </p>
                </div>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <AppInput
                  v-model.number="entry.quantity"
                  label="Quantity"
                  type="number"
                  min="1"
                  :error="fieldErrors[`combo_entries.${idx}.quantity`]"
                />
                <AppInput
                  v-model="entry.modifier_label"
                  label="Modifier (optional)"
                  type="text"
                  placeholder="e.g. No ice"
                  :error="fieldErrors[`combo_entries.${idx}.modifier_label`]"
                />
              </div>
            </li>
          </ul>
        </section>
      </template>

      <!-- With variants: option groups + SKU table -->
      <template v-if="form.type === 'with_variants'">
        <section class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="font-semibold text-charcoal dark:text-white">Option groups</h3>
            <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px]" @click="addOptionGroup">
              <template #icon><span class="material-icons">add</span></template>
              Add group
            </AppButton>
          </div>
          <p v-if="fieldErrors.variant_option_groups" class="text-sm text-red-600 dark:text-red-400">{{ fieldErrors.variant_option_groups }}</p>
          <ul class="space-y-4">
            <li
              v-for="(grp, gIdx) in form.variant_option_groups"
              :key="gIdx"
              class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 space-y-3"
            >
              <div class="flex items-center justify-between gap-2">
                <AppInput
                  v-model="grp.name"
                  :label="`Group name (e.g. ${gIdx === 0 ? 'Size' : 'Type'})`"
                  type="text"
                  placeholder="e.g. Size"
                />
                <AppButton
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="min-h-[44px] min-w-[44px] text-red-600 shrink-0"
                  aria-label="Remove group"
                  @click="removeOptionGroup(gIdx)"
                >
                  <span class="material-icons">remove_circle_outline</span>
                </AppButton>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Values (one per line or comma-separated)</label>
                <textarea
                  v-model="grp.valuesText"
                  rows="2"
                  class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 py-3 px-4 text-charcoal dark:text-white min-h-[44px]"
                  placeholder="Small, Medium, Large"
                  @input="syncGroupValues(gIdx)"
                />
              </div>
            </li>
          </ul>
        </section>
        <section v-if="cartesianSkus.length > 0" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6 overflow-x-auto">
          <h3 class="font-semibold text-charcoal dark:text-white mb-3">Variant prices</h3>
          <p v-if="fieldErrors.variant_skus" class="text-sm text-red-600 dark:text-red-400 mb-2">{{ fieldErrors.variant_skus }}</p>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700">
                <th scope="col" class="text-left py-2 px-2 font-semibold text-charcoal dark:text-white">Combination</th>
                <th scope="col" class="text-left py-2 px-2 font-semibold text-charcoal dark:text-white">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, rIdx) in form.variant_skus"
                :key="rIdx"
                class="border-b border-slate-100 dark:border-slate-800"
              >
                <td class="py-3 px-2 text-charcoal dark:text-white">{{ row.label }}</td>
                <td class="py-3 px-2">
                  <input
                    v-model.number="row.price"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    :aria-label="`Price for ${row.label || 'variant'}`"
                    class="w-full min-w-[5rem] min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 px-3 py-2 text-charcoal dark:text-white"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </template>

      <div class="flex flex-col-reverse sm:flex-row gap-3">
        <router-link :to="{ name: 'MenuItems' }">
          <AppButton type="button" variant="secondary" class="min-h-[44px]">Cancel</AppButton>
        </router-link>
        <AppButton
          type="submit"
          variant="primary"
          class="min-h-[44px]"
          :disabled="saving"
        >
          <template v-if="saving" #icon>
            <span class="material-icons animate-spin">sync</span>
          </template>
          {{ saving ? 'Creating…' : 'Create item' }}
        </AppButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import MenuItem from '@/models/MenuItem.js'
import { menuItemService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const toastStore = useToastStore()

const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})
const catalogItems = ref([])

const form = reactive({
  type: 'simple',
  name: '',
  description: '',
  price: '',
  combo_price: '',
  combo_entries: [],
  variant_option_groups: [],
  variant_skus: [],
})

function defaultLocale(item) {
  const locs = item?.translations ? Object.keys(item.translations) : []
  return locs[0] ?? 'en'
}

function catalogItemName(item) {
  if (item?.effectiveName) return item.effectiveName(defaultLocale(item))
  const t = item?.translations?.[defaultLocale(item)]
  return t?.name ?? item?.uuid ?? '—'
}

function selectedCatalogItemHasVariants(menuItemUuid) {
  const item = catalogItems.value.find((i) => i.uuid === menuItemUuid)
  return item?.type === 'with_variants' && Array.isArray(item?.variant_skus) && item.variant_skus.length > 0
}

function variantSkusForItem(menuItemUuid) {
  const item = catalogItems.value.find((i) => i.uuid === menuItemUuid)
  return item?.variant_skus ?? []
}

function optionValuesLabel(ov) {
  if (!ov || typeof ov !== 'object') return '—'
  return Object.values(ov).filter(Boolean).join(', ')
}

const ADD_THROTTLE_MS = 250
let lastComboEntryAdd = 0
let lastOptionGroupAdd = 0

function addComboEntry() {
  const now = Date.now()
  if (now - lastComboEntryAdd < ADD_THROTTLE_MS) return
  lastComboEntryAdd = now
  form.combo_entries.push({
    menu_item_uuid: '',
    variant_uuid: null,
    quantity: 1,
    modifier_label: '',
  })
}

function removeComboEntry(idx) {
  form.combo_entries.splice(idx, 1)
}

function addOptionGroup() {
  const now = Date.now()
  if (now - lastOptionGroupAdd < ADD_THROTTLE_MS) return
  lastOptionGroupAdd = now
  form.variant_option_groups.push({ name: '', values: [], valuesText: '' })
}

function removeOptionGroup(idx) {
  form.variant_option_groups.splice(idx, 1)
  updateCartesianSkus()
}

function syncGroupValues(gIdx) {
  const grp = form.variant_option_groups[gIdx]
  if (!grp) return
  const text = (grp.valuesText ?? '').trim()
  grp.values = text ? text.split(/[\n,]+/).map((v) => v.trim()).filter(Boolean) : []
  updateCartesianSkus()
}

function cartesianProduct(groups) {
  if (!groups.length) return []
  const [first, ...rest] = groups
  const firstCombos = (first?.values ?? []).map((v) => ({ [first?.name ?? '']: v }))
  if (rest.length === 0) return firstCombos
  const restCombos = cartesianProduct(rest)
  return firstCombos.flatMap((opt) => restCombos.map((r) => ({ ...opt, ...r })))
}

const cartesianSkus = computed(() => {
  const groups = form.variant_option_groups.filter((g) => (g.name ?? '').trim() && (g.values ?? []).length > 0)
  return cartesianProduct(groups)
})

function updateCartesianSkus() {
  const combos = cartesianProduct(
    form.variant_option_groups.filter((g) => (g.name ?? '').trim() && (g.values ?? []).length > 0)
  )
  const existingByKey = {}
  for (const row of form.variant_skus) {
    const key = JSON.stringify(row.option_values || row)
    existingByKey[key] = row.price
  }
  form.variant_skus = combos.map((opt) => ({
    option_values: opt,
    label: Object.values(opt).filter(Boolean).join(', '),
    price: existingByKey[JSON.stringify(opt)] ?? '',
  }))
}

watch(
  () => form.variant_option_groups.map((g) => ({ name: g.name, len: (g.values ?? []).length })),
  () => { if (cartesianSkus.value.length > 0) updateCartesianSkus() },
  { deep: true }
)

watch(() => form.type, () => {
  fieldErrors.value = {}
})

function validate() {
  const err = {}
  if (!(form.name ?? '').trim()) err.name = 'Name is required.'

  if (form.type === 'simple') {
    const priceNum = form.price === '' || form.price == null ? null : Number(form.price)
    if (priceNum !== null && (Number.isNaN(priceNum) || priceNum < 0)) err.price = 'Price must be 0 or greater.'
  }

  if (form.type === 'combo') {
    const priceNum = form.combo_price === '' || form.combo_price == null ? null : Number(form.combo_price)
    if (priceNum !== null && (Number.isNaN(priceNum) || priceNum < 0)) err.combo_price = 'Combo price must be 0 or greater.'
    if (!form.combo_entries.length) err.combo_entries = 'Add at least one combo entry.'
    form.combo_entries.forEach((entry, idx) => {
      if (!(entry.menu_item_uuid ?? '').trim()) err[`combo_entries.${idx}.menu_item_uuid`] = 'Select a menu item.'
      else if (selectedCatalogItemHasVariants(entry.menu_item_uuid) && !(entry.variant_uuid ?? '')) {
        err[`combo_entries.${idx}.variant_uuid`] = 'Select a variant for this item.'
      }
      const q = entry.quantity != null ? Number(entry.quantity) : 1
      if (Number.isNaN(q) || q < 1) err[`combo_entries.${idx}.quantity`] = 'Quantity must be at least 1.'
    })
  }

  if (form.type === 'with_variants') {
    const groups = form.variant_option_groups.filter((g) => (g.name ?? '').trim() && (g.values ?? []).length > 0)
    if (!groups.length) err.variant_option_groups = 'Add at least one option group with values.'
    const missingPrice = form.variant_skus.find((s) => s.price === '' || s.price == null || Number.isNaN(Number(s.price)) || Number(s.price) < 0)
    if (missingPrice && form.variant_skus.length > 0) err.variant_skus = 'Set a price for every variant.'
  }

  fieldErrors.value = err
  return Object.keys(err).length === 0
}

function buildPayload() {
  const payload = {
    sort_order: 0,
    type: form.type,
    translations: {
      en: {
        name: form.name.trim(),
        description: (form.description ?? '').trim() || null,
      },
    },
  }
  if (form.type === 'simple') {
    const price = form.price === '' || form.price == null ? null : Number(form.price)
    if (price != null && !Number.isNaN(price)) payload.price = price
  }
  if (form.type === 'combo') {
    const comboPrice = form.combo_price === '' || form.combo_price == null ? null : Number(form.combo_price)
    if (comboPrice != null && !Number.isNaN(comboPrice)) payload.combo_price = comboPrice
    payload.combo_entries = form.combo_entries.map((e) => ({
      menu_item_uuid: e.menu_item_uuid,
      variant_uuid: e.variant_uuid || null,
      quantity: Math.max(1, Number(e.quantity) || 1),
      modifier_label: (e.modifier_label ?? '').trim() || null,
    }))
  }
  if (form.type === 'with_variants') {
    payload.variant_option_groups = form.variant_option_groups
      .filter((g) => (g.name ?? '').trim() && (g.values ?? []).length > 0)
      .map((g) => ({ name: g.name.trim(), values: g.values }))
    payload.variant_skus = form.variant_skus.map((s) => ({
      option_values: s.option_values,
      price: Number(s.price),
      image_url: s.image_url ?? null,
    }))
  }
  return payload
}

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = {}
  if (!validate()) return
  saving.value = true
  try {
    await menuItemService.create(buildPayload())
    toastStore.success('Menu item created.')
    router.push({ name: 'MenuItems' })
  } catch (e) {
    const errs = getValidationErrors(e)
    if (Object.keys(errs).length > 0) fieldErrors.value = errs
    error.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    saving.value = false
  }
}

async function loadCatalog() {
  try {
    const res = await menuItemService.list()
    const raw = res.data ?? []
    catalogItems.value = raw.map((i) => MenuItem.fromApi({ data: i }))
  } catch {
    catalogItems.value = []
  }
}

onMounted(() => {
  loadCatalog()
})
</script>
