<template>
  <div data-testid="restaurant-settings">
    <div v-if="loading" class="space-y-4" data-testid="settings-loading">
      <div class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-64 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="!embed && !restaurant"
      class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">{{ $t('app.restaurantNotFound') }}</p>
      <AppBackLink to="/app/restaurants" />
    </div>

    <template v-else-if="restaurant">
      <header class="mb-4 lg:mb-6">
        <div>
          <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">Settings</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Language, currency, and description by language.</p>
        </div>
      </header>

      <div
        id="content-form-error"
        role="alert"
        aria-live="polite"
        data-testid="settings-error"
        class="mb-6 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
        :class="{ 'sr-only': !error }"
      >
        {{ error }}
      </div>

      <!-- Currency + Primary color: stacked on mobile, side by side from md -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <section class="min-w-0 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6" data-testid="settings-section-currency">
          <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Currency</h3>
          <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Currency used for prices on your menu and public site.</p>
          <div class="flex flex-wrap items-center gap-3">
            <label for="restaurant-currency" class="text-sm font-medium text-charcoal dark:text-white shrink-0">Currency</label>
            <select
              id="restaurant-currency"
              v-model="currency"
              data-testid="settings-currency-select"
              class="max-w-[280px] min-h-[44px] rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary focus:outline-none"
              :disabled="savingCurrency"
              @change="saveCurrency"
            >
              <option v-for="c in CURRENCIES" :key="c.code" :value="c.code">{{ c.symbol }} {{ c.name }}</option>
            </select>
            <span v-if="savingCurrency" class="text-sm text-slate-500">Saving…</span>
          </div>
        </section>

        <section class="min-w-0 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6" data-testid="settings-section-primary-color">
          <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Primary color</h3>
          <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Accent color used on your public restaurant page (buttons, links, highlights). Leave empty to use the default.</p>
          <div class="flex flex-wrap items-center gap-3">
            <input
              v-model="primaryColorHex"
              type="color"
              class="w-14 h-14 min-w-[56px] min-h-[56px] rounded-xl border-slate-200 dark:border-zinc-600 cursor-pointer bg-white dark:bg-zinc-800"
              aria-label="Pick primary color"
              data-testid="settings-primary-color-picker"
              @input="onPrimaryColorInput"
            />
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 min-w-0">
              <label for="restaurant-primary-color-hex" class="text-sm font-medium text-charcoal dark:text-white shrink-0">Hex</label>
              <input
                id="restaurant-primary-color-hex"
                v-model="primaryColorHex"
                type="text"
                maxlength="9"
                placeholder="#ee4b2b"
                class="w-full sm:w-32 min-h-[44px] rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 px-4 text-charcoal dark:text-white text-sm font-mono focus:ring-2 focus:ring-primary focus:outline-none"
                data-testid="settings-primary-color-hex"
                @input="onPrimaryColorHexInput"
              />
            </div>
            <AppButton
              type="button"
              variant="secondary"
              size="sm"
              class="min-h-[44px]"
              :disabled="savingPrimaryColor || !restaurant?.primary_color"
              data-testid="settings-primary-color-clear"
              @click="clearPrimaryColor"
            >
              {{ savingPrimaryColor ? 'Saving…' : 'Clear' }}
            </AppButton>
            <AppButton
              v-if="primaryColorHex && primaryColorHex !== (restaurant?.primary_color || '')"
              type="button"
              variant="primary"
              size="sm"
              class="min-h-[44px]"
              :disabled="savingPrimaryColor || !isValidPrimaryColor(primaryColorHex)"
              data-testid="settings-primary-color-save"
              @click="savePrimaryColor"
            >
              {{ savingPrimaryColor ? 'Saving…' : 'Save' }}
            </AppButton>
          </div>
          <p v-if="primaryColorError" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ primaryColorError }}</p>
        </section>
      </div>

      <!-- Languages -->
      <section class="mb-8 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6" data-testid="settings-section-languages">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Languages</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Add or remove languages. The default language is used when no language is selected on the public site.</p>
        <ul class="space-y-2 mb-4">
          <li
            v-for="loc in visibleLanguageRows"
            :key="loc"
            class="flex items-center justify-between gap-2 py-2 px-4 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-700/50"
          >
            <span class="font-medium text-charcoal dark:text-white">{{ getLocaleDisplay(loc) }}</span>
            <span v-if="loc === restaurant.default_locale" class="text-xs text-slate-500 dark:text-slate-400">Default</span>
            <div v-else class="flex items-center gap-2">
              <AppButton
                type="button"
                variant="ghost"
                size="sm"
                class="min-h-[44px]"
                :disabled="savingDefault === loc"
                data-testid="settings-set-default-button"
                @click="setDefaultLocale(loc)"
              >
                {{ savingDefault === loc ? 'Saving…' : 'Set as default' }}
              </AppButton>
              <AppButton
                type="button"
                variant="ghost"
                size="sm"
                class="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 min-h-[44px]"
                :disabled="removingLocale === loc"
                @click="confirmRemoveLanguage(loc)"
              >
                {{ removingLocale === loc ? 'Removing…' : 'Remove' }}
              </AppButton>
            </div>
          </li>
        </ul>
        <div v-if="installedLanguages.length > 5" class="mb-4">
          <AppButton
            v-if="!languagesExpanded"
            type="button"
            variant="ghost"
            size="sm"
            class="min-h-[44px]"
            data-testid="settings-show-all-languages"
            @click="languagesExpanded = true"
          >
            Show all languages ({{ installedLanguages.length }} total)
          </AppButton>
          <AppButton
            v-else
            type="button"
            variant="ghost"
            size="sm"
            class="min-h-[44px]"
            data-testid="settings-show-less-languages"
            @click="languagesExpanded = false"
          >
            Show less
          </AppButton>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <select
            v-model="languageToAdd"
            data-testid="settings-add-language-select"
            class="w-full md:max-w-[280px] min-h-[44px] rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary focus:outline-none"
            :disabled="addingLanguage"
          >
            <option value="">Add language…</option>
            <option v-for="loc in availableLocalesToAdd" :key="loc" :value="loc">{{ getLocaleDisplay(loc) }}</option>
          </select>
          <AppButton type="button" variant="primary" size="sm" class="min-h-[44px]" data-testid="settings-add-language-button" :disabled="!languageToAdd || addingLanguage" @click="addLanguage">
            {{ addingLanguage ? 'Adding…' : 'Add' }}
          </AppButton>
        </div>
      </section>

      <!-- Description per locale -->
      <section class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6" data-testid="settings-section-descriptions">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Description by language</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Optional description shown per language on the public site.</p>
        <p v-if="!installedLanguages.length" class="text-sm text-slate-500 dark:text-slate-400">Add at least one language above to manage descriptions.</p>
        <template v-else>
          <div class="mb-4">
            <label for="settings-description-locale" class="block text-sm font-medium text-charcoal dark:text-white mb-2">Edit description for:</label>
            <select
              id="settings-description-locale"
              v-model="selectedDescriptionLocale"
              aria-label="Edit description for language"
              data-testid="settings-description-locale-select"
              class="w-full min-h-[44px] rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 px-4 text-charcoal dark:text-white focus:ring-2 focus:ring-primary focus:outline-none"
              @change="onDescriptionLocaleChange"
            >
              <option
                v-for="loc in installedLanguages"
                :key="loc"
                :value="loc"
              >
                {{ getLocaleDisplay(loc) }}{{ loc === defaultLocale ? ' (Default)' : '' }}
              </option>
            </select>
          </div>
          <textarea
            ref="descriptionTextareaRef"
            :value="currentDescriptionValue"
            rows="4"
            :readonly="!!translatingLocale"
            :data-testid="`settings-description-${selectedDescriptionLocale}`"
            class="w-full rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-y mb-4"
            :class="{ 'opacity-70 cursor-wait': !!translatingLocale }"
            :placeholder="`Description (${getLocaleDisplay(selectedDescriptionLocale)})`"
            :aria-busy="!!translatingLocale"
            @input="onDescriptionInput"
          />
          <div class="flex flex-wrap items-center justify-end gap-2">
            <AppButton
              v-if="selectedDescriptionLocale !== defaultLocale"
              type="button"
              variant="secondary"
              size="sm"
              class="min-h-[44px]"
              :disabled="!!translatingLocale"
              :aria-busy="translatingLocale === selectedDescriptionLocale"
              :aria-label="translatingLocale === selectedDescriptionLocale ? 'Translating description from default language' : 'Translate from default language'"
              data-testid="settings-translate-from-default"
              @click="translateDescription(selectedDescriptionLocale)"
            >
              <template #icon>
                <span
                  v-if="translatingLocale === selectedDescriptionLocale"
                  class="material-icons animate-spin text-lg"
                  aria-hidden="true"
                >sync</span>
                <span
                  v-else
                  class="material-icons text-lg"
                  aria-hidden="true"
                >translate</span>
              </template>
              {{ translatingLocale === selectedDescriptionLocale ? 'Translating…' : 'Translate from default' }}
            </AppButton>
            <AppButton
              type="button"
              variant="primary"
              size="sm"
              class="min-h-[44px]"
              :disabled="savingLocale === selectedDescriptionLocale"
              data-testid="settings-description-save"
              @click="saveDescription(selectedDescriptionLocale)"
            >
              {{ savingLocale === selectedDescriptionLocale ? 'Saving…' : 'Save' }}
            </AppButton>
          </div>
        </template>
      </section>

      <!-- Public page template -->
      <section class="mt-8 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6" data-testid="settings-section-template">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Public page template</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Choose the layout for your public restaurant page. This affects how your menu and info are displayed to visitors.</p>
        <p v-if="savingTemplate" class="text-sm text-slate-500 dark:text-slate-400 mb-4" aria-live="polite">Saving…</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" role="group" aria-label="Select template">
          <button
            v-for="opt in TEMPLATE_OPTIONS"
            :key="opt.id"
            type="button"
            :data-testid="`settings-template-${opt.id}`"
            class="relative flex flex-col items-start p-4 sm:p-5 rounded-xl border-2 text-left min-h-[44px] touch-manipulation transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
            :class="currentTemplate === opt.id
              ? 'border-primary bg-primary/5 dark:bg-primary/10'
              : 'border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800/50 hover:border-slate-300 dark:hover:border-zinc-600'"
            :aria-pressed="currentTemplate === opt.id"
            :disabled="savingTemplate"
            @click="selectTemplate(opt.id)"
          >
            <span class="absolute top-3 right-3 flex h-6 w-6 items-center justify-center rounded-full" :class="currentTemplate === opt.id ? 'bg-primary text-white' : 'bg-slate-200 dark:bg-zinc-600'">
              <span v-if="currentTemplate === opt.id" class="material-icons text-sm" aria-hidden="true">check</span>
            </span>
            <!-- Placeholder for template preview image (user can add later) -->
            <div class="w-full aspect-[4/3] rounded-lg bg-slate-200 dark:bg-zinc-700 mb-3 flex items-center justify-center" aria-hidden="true">
              <span class="material-icons text-3xl text-slate-400 dark:text-slate-500">dashboard</span>
            </div>
            <span class="font-semibold text-charcoal dark:text-white">{{ opt.name }}</span>
            <span class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ opt.description }}</span>
          </button>
        </div>
        <p v-if="templateError" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert">{{ templateError }}</p>
      </section>

      <!-- Confirm remove language modal -->
      <div
        v-if="localeToRemove"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="remove-lang-title"
        data-testid="settings-remove-language-modal"
      >
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl max-w-sm w-full p-6">
          <h3 id="remove-lang-title" class="font-bold text-charcoal dark:text-white mb-2">Remove language?</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
            {{ getLocaleDisplay(localeToRemove) }} will be removed. Description for this language will be deleted.
          </p>
          <div class="flex gap-3">
            <AppButton variant="secondary" class="flex-1" data-testid="settings-remove-language-cancel" :disabled="removingLocale" @click="localeToRemove = null">Cancel</AppButton>
            <AppButton variant="primary" class="flex-1 bg-red-600 hover:bg-red-700" data-testid="settings-remove-language-confirm" :disabled="removingLocale" @click="doRemoveLanguage">
              {{ removingLocale ? 'Removing…' : 'Remove' }}
            </AppButton>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import AppBackLink from '@/components/AppBackLink.vue'
import { LOCALE_CODES, getLocaleDisplay } from '@/config/locales'
import Restaurant from '@/models/Restaurant.js'
import { restaurantService, localeService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'
import { useBreadcrumbStore } from '@/stores/breadcrumb'

const props = defineProps({
  embed: { type: Boolean, default: false },
  defaultDescription: { type: String, default: '' },
})

const emit = defineEmits(['update:defaultDescription'])

const route = useRoute()
const toastStore = useToastStore()
const breadcrumbStore = useBreadcrumbStore()

const uuid = computed(() => route.params.uuid)

const ALLOWED_TEMPLATES = ['template-1', 'template-2']

const TEMPLATE_OPTIONS = [
  { id: 'template-1', name: 'Template 1', description: 'Warm, card-based layout with accent color and rounded sections.' },
  { id: 'template-2', name: 'Template 2', description: 'Minimal, clean layout with simple typography and flat sections.' },
]

const CURRENCIES = [
  { code: 'USD', name: 'US Dollar', symbol: '$' },
  { code: 'EUR', name: 'Euro', symbol: '€' },
  { code: 'GBP', name: 'British Pound', symbol: '£' },
  { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$' },
  { code: 'AUD', name: 'Australian Dollar', symbol: 'A$' },
  { code: 'JPY', name: 'Japanese Yen', symbol: '¥' },
  { code: 'CHF', name: 'Swiss Franc', symbol: 'Fr' },
  { code: 'NGN', name: 'Nigerian Naira', symbol: '₦' },
  { code: 'MXN', name: 'Mexican Peso', symbol: 'MX$' },
  { code: 'BRL', name: 'Brazilian Real', symbol: 'R$' },
]

const loading = ref(true)
const restaurant = ref(null)
const error = ref('')
const currency = ref('USD')
const savingCurrency = ref(false)
const primaryColorHex = ref('')
const primaryColorError = ref('')
const savingPrimaryColor = ref(false)
const savingDefault = ref(null)
const addingLanguage = ref(false)
const removingLocale = ref(null)
const localeToRemove = ref(null)
const savingLocale = ref(null)
const translatingLocale = ref(null)
const descriptions = ref({})
const languageToAdd = ref('')
const selectedDescriptionLocale = ref('')
const descriptionTextareaRef = ref(null)
const languagesExpanded = ref(false)
const savingTemplate = ref(false)
const templateError = ref('')

const defaultLocale = computed(() => restaurant.value?.default_locale ?? '')

const visibleLanguageRows = computed(() => {
  const list = installedLanguages.value
  if (list.length <= 5 || languagesExpanded.value) return list
  return list.slice(0, 5)
})

const currentDescriptionValue = computed(() => {
  const loc = selectedDescriptionLocale.value
  if (!loc) return ''
  if (props.embed && loc === defaultLocale.value) return props.defaultDescription
  return descriptions.value[loc] ?? ''
})

const installedLanguages = computed(() => {
  const lang = restaurant.value?.languages ?? []
  const def = restaurant.value?.default_locale
  if (!def || lang.length <= 1) return lang
  return [def, ...lang.filter((l) => l !== def)]
})

const availableLocalesToAdd = computed(() => {
  const installed = new Set(installedLanguages.value)
  return LOCALE_CODES.filter((code) => !installed.has(code))
})

const currentTemplate = computed(() => {
  const t = restaurant.value?.template
  return ALLOWED_TEMPLATES.includes(t) ? t : 'template-1'
})

async function loadRestaurant() {
  if (!uuid.value) return
  loading.value = true
  error.value = ''
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
    if (restaurant.value) {
      currency.value = restaurant.value.currency ?? 'USD'
      primaryColorHex.value = restaurant.value.primary_color ?? ''
      await loadAllDescriptions()
    }
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
    else error.value = normalizeApiError(e).message
  } finally {
    loading.value = false
  }
}

async function loadAllDescriptions() {
  const locs = restaurant.value?.languages ?? []
  const next = { ...descriptions.value }
  for (const loc of locs) {
    try {
      const res = await restaurantService.getTranslation(uuid.value, loc)
      next[loc] = res.data?.description ?? ''
    } catch {
      next[loc] = ''
    }
  }
  descriptions.value = next
  if (props.embed && restaurant.value?.default_locale != null) {
    emit('update:defaultDescription', next[restaurant.value.default_locale] ?? '')
  }
  const def = restaurant.value?.default_locale
  if (locs.length && (!selectedDescriptionLocale.value || !locs.includes(selectedDescriptionLocale.value))) {
    selectedDescriptionLocale.value = def ?? locs[0]
  }
}

async function saveCurrency() {
  if (savingCurrency.value) return
  if (!restaurant.value?.uuid) return
  savingCurrency.value = true
  error.value = ''
  try {
    await restaurantService.update(uuid.value, { currency: currency.value })
    if (restaurant.value) restaurant.value.currency = currency.value
    toastStore.success('Currency updated.')
  } catch (e) {
    const errs = getValidationErrors(e)
    error.value = Object.keys(errs).length ? Object.values(errs).join(' ') : normalizeApiError(e).message
  } finally {
    savingCurrency.value = false
  }
}

const PRIMARY_COLOR_REGEX = /^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3}([0-9A-Fa-f]{2})?)?$/

function isValidPrimaryColor(hex) {
  if (!hex || typeof hex !== 'string') return false
  const trimmed = hex.trim()
  return trimmed === '' || PRIMARY_COLOR_REGEX.test(trimmed)
}

function onPrimaryColorInput(e) {
  const v = e.target?.value ?? ''
  primaryColorHex.value = v
  primaryColorError.value = ''
}

function onPrimaryColorHexInput(e) {
  const v = (e.target?.value ?? '').trim()
  primaryColorHex.value = v
  if (v && !PRIMARY_COLOR_REGEX.test(v)) {
    primaryColorError.value = 'Enter a valid hex color (e.g. #ee4b2b or #fff).'
  } else {
    primaryColorError.value = ''
  }
}

async function savePrimaryColor() {
  if (savingPrimaryColor.value) return
  if (!restaurant.value?.uuid || !isValidPrimaryColor(primaryColorHex.value)) return
  const hex = primaryColorHex.value.trim()
  savingPrimaryColor.value = true
  primaryColorError.value = ''
  error.value = ''
  try {
    await restaurantService.update(uuid.value, { primary_color: hex || null })
    if (restaurant.value) restaurant.value.primary_color = hex || null
    toastStore.success('Primary color updated.')
  } catch (e) {
    const errs = getValidationErrors(e)
    primaryColorError.value = Object.keys(errs).length ? Object.values(errs).join(' ') : normalizeApiError(e).message
  } finally {
    savingPrimaryColor.value = false
  }
}

async function clearPrimaryColor() {
  if (savingPrimaryColor.value) return
  if (!restaurant.value?.uuid) return
  savingPrimaryColor.value = true
  primaryColorError.value = ''
  error.value = ''
  try {
    await restaurantService.update(uuid.value, { primary_color: null })
    if (restaurant.value) restaurant.value.primary_color = null
    primaryColorHex.value = ''
    toastStore.success('Primary color cleared.')
  } catch (e) {
    const errs = getValidationErrors(e)
    error.value = Object.keys(errs).length ? Object.values(errs).join(' ') : normalizeApiError(e).message
  } finally {
    savingPrimaryColor.value = false
  }
}

async function setDefaultLocale(loc) {
  if (!restaurant.value || restaurant.value.default_locale === loc) return
  if (savingDefault.value != null) return // prevent double run (e.g. double-click) and second toast
  savingDefault.value = loc
  error.value = ''
  try {
    const res = await restaurantService.update(uuid.value, { default_locale: loc })
    if (res) restaurant.value = Restaurant.fromApi(res).toJSON()
    // Sync parent/Profile to the new default's description so it doesn't show the old default's value
    if (props.embed) emit('update:defaultDescription', descriptions.value[loc] ?? '')
    toastStore.success('Default language updated.')
  } catch (e) {
    const errs = getValidationErrors(e)
    error.value = Object.keys(errs).length ? Object.values(errs).join(' ') : normalizeApiError(e).message
  } finally {
    savingDefault.value = null
  }
}

async function addLanguage() {
  if (!languageToAdd.value) return
  addingLanguage.value = true
  error.value = ''
  const localeBeingAdded = languageToAdd.value
  try {
    const res = await restaurantService.addLanguage(uuid.value, { locale: localeBeingAdded })
    if (res.data) restaurant.value.languages = [...res.data]
    languageToAdd.value = ''
    descriptions.value[localeBeingAdded] = ''
    toastStore.success(`${getLocaleDisplay(localeBeingAdded)} added.`)
  } catch (e) {
    const msg = normalizeApiError(e).message ?? ''
    // Duplicate submit can return "already installed" after the first request succeeded; don't show as error.
    if (msg.toLowerCase().includes('already installed') && (restaurant.value?.languages ?? []).includes(localeBeingAdded)) {
      languageToAdd.value = ''
    } else {
      error.value = msg
    }
  } finally {
    addingLanguage.value = false
  }
}

function confirmRemoveLanguage(loc) {
  localeToRemove.value = loc
}

async function doRemoveLanguage() {
  if (!localeToRemove.value || removingLocale.value) return
  removingLocale.value = localeToRemove.value
  try {
    await restaurantService.removeLanguage(uuid.value, localeToRemove.value)
    restaurant.value.languages = (restaurant.value.languages ?? []).filter((l) => l !== localeToRemove.value)
    delete descriptions.value[localeToRemove.value]
    if (selectedDescriptionLocale.value === localeToRemove.value) {
      const remaining = restaurant.value.languages ?? []
      selectedDescriptionLocale.value = restaurant.value.default_locale ?? remaining[0] ?? ''
    }
    toastStore.success('Language removed.')
    localeToRemove.value = null
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    removingLocale.value = null
  }
}

async function saveDescription(loc) {
  if (savingLocale.value != null) return
  savingLocale.value = loc
  try {
    await restaurantService.putTranslation(uuid.value, loc, { description: descriptions.value[loc] ?? null })
    toastStore.success('Description saved.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    savingLocale.value = null
  }
}

function selectTemplate(templateId) {
  if (savingTemplate.value) return
  if (!ALLOWED_TEMPLATES.includes(templateId)) {
    templateError.value = 'Please choose a valid template.'
    return
  }
  if (currentTemplate.value === templateId) return
  templateError.value = ''
  doSaveTemplate(templateId)
}

async function doSaveTemplate(templateId) {
  if (!restaurant.value?.uuid) return
  savingTemplate.value = true
  error.value = ''
  templateError.value = ''
  try {
    const res = await restaurantService.update(uuid.value, { template: templateId })
    if (res?.data) restaurant.value = Restaurant.fromApi(res).toJSON()
    toastStore.success('Template updated.')
  } catch (e) {
    const errs = getValidationErrors(e)
    templateError.value = Object.keys(errs).length ? Object.values(errs).join(' ') : normalizeApiError(e).message
  } finally {
    savingTemplate.value = false
  }
}

/** Translate default locale description into this locale via API; fill local state. User clicks Save to persist. */
async function translateDescription(loc) {
  const def = restaurant.value?.default_locale
  if (!def || def === loc) return
  const defDesc = (props.embed ? props.defaultDescription : null) ?? descriptions.value[def] ?? ''
  const trimmed = (defDesc ?? '').trim()
  if (!trimmed) {
    toastStore.info('Default description is empty. Add a description for the default language first.')
    return
  }
  translatingLocale.value = loc
  error.value = ''
  try {
    const data = await localeService.translate({
      text: trimmed,
      from_locale: def,
      to_locale: loc,
    })
    const translated = data?.translated_text ?? ''
    const next = { ...descriptions.value }
    next[loc] = translated
    descriptions.value = next
    if (data?.fallback === true) {
      toastStore.info('Translation not available for this language. Original text shown — you can edit it.')
    } else {
      toastStore.success('Translation applied. Click Save to store.')
    }
  } catch (e) {
    const msg = normalizeApiError(e).message ?? 'Translation failed.'
    error.value = msg
    toastStore.error(msg)
  } finally {
    translatingLocale.value = null
  }
}

function onDescriptionInput(e) {
  const v = e.target.value
  const loc = selectedDescriptionLocale.value
  if (!loc) return
  const next = { ...descriptions.value }
  next[loc] = v
  descriptions.value = next
  if (props.embed && loc === defaultLocale.value) emit('update:defaultDescription', v)
}

function onDescriptionLocaleChange() {
  nextTick(() => {
    descriptionTextareaRef.value?.focus()
  })
}

// When embed, keep default locale description in sync with Profile tab
watch(() => props.defaultDescription, (val) => {
  if (props.embed && restaurant.value?.default_locale != null) {
    const next = { ...descriptions.value }
    next[restaurant.value.default_locale] = val ?? ''
    descriptions.value = next
  }
}, { immediate: true })

onMounted(() => loadRestaurant())
watch(uuid, () => loadRestaurant())
</script>
