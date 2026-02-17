<template>
  <div class="max-w-3xl">
    <div v-if="loading && isEdit" class="space-y-4">
      <div class="h-24 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-64 rounded-2xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="isEdit && !loading && !restaurant"
      class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant or menu item not found.</p>
      <AppBackLink :to="{ name: 'RestaurantMenuItems', params: { uuid } }" />
    </div>

    <template v-else>
      <header class="mb-6 lg:mb-8">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">
          {{ isEdit ? 'Edit menu item' : 'Add menu item' }}
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          {{ isEdit ? 'Name and description per language. At least the default language is required.' : 'Add the name and optional description. You can add other languages after creating the item.' }}
        </p>
      </header>

      <form v-if="restaurant" class="space-y-6 lg:space-y-8" novalidate @submit.prevent="handleSubmit">
        <div
          id="menu-item-form-error"
          role="alert"
          aria-live="polite"
          class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
          :class="{ 'sr-only': !error }"
        >
          {{ error }}
        </div>

        <section
          v-for="loc in formLocales"
          :key="loc"
          class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4"
        >
          <div class="flex items-center justify-between gap-2">
            <h3 class="font-semibold text-charcoal dark:text-white">{{ getLocaleDisplay(loc) }}</h3>
            <AppButton
              v-if="loc !== restaurant.default_locale"
              type="button"
              variant="ghost"
              size="sm"
              class="min-h-[36px]"
              :disabled="translatingLocale === loc"
              @click="translateLocale(loc)"
            >
              {{ translatingLocale === loc ? 'Translating…' : 'Translate from default' }}
            </AppButton>
          </div>
          <AppInput
            v-model="form.translations[loc].name"
            :label="`Name (${getLocaleDisplay(loc)})`"
            type="text"
            :placeholder="loc === restaurant.default_locale ? 'e.g. Margherita Pizza' : ''"
            :error="fieldErrors[`translations.${loc}.name`]"
          />
          <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Description (optional)</label>
            <textarea
              v-model="form.translations[loc].description"
              rows="3"
              class="w-full rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-y"
              :placeholder="`Description in ${getLocaleDisplay(loc)}`"
            />
          </div>
        </section>

        <div class="flex flex-col-reverse sm:flex-row gap-3">
          <router-link :to="{ name: 'RestaurantMenuItems', params: { uuid } }" class="sm:mr-auto">
            <AppButton type="button" variant="secondary" class="w-full sm:w-auto min-h-[44px]">Cancel</AppButton>
          </router-link>
          <AppButton
            type="submit"
            variant="primary"
            class="w-full sm:w-auto min-h-[44px]"
            :disabled="saving"
          >
            <template v-if="saving" #icon>
              <span class="material-icons animate-spin">sync</span>
            </template>
            {{ saving ? 'Saving…' : (isEdit ? 'Save changes' : 'Create item') }}
          </AppButton>
        </div>
      </form>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBackLink from '@/components/AppBackLink.vue'
import { getLocaleDisplay } from '@/config/locales'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import { restaurantService, localeService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const toastStore = useToastStore()
const breadcrumbStore = useBreadcrumbStore()

const uuid = computed(() => route.params.uuid)
const itemUuid = computed(() => route.params.itemUuid)
const isEdit = computed(() => route.meta.mode === 'edit')

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})
const restaurant = ref(null)
const translatingLocale = ref(null)

const form = reactive({
  sort_order: 0,
  translations: {},
})

const installedLanguages = computed(() => restaurant.value?.languages ?? [])

/** On create: only default language. On edit: all installed languages. */
const formLocales = computed(() => {
  const def = restaurant.value?.default_locale
  const all = installedLanguages.value
  if (isEdit.value) return all
  return def ? [def] : []
})

function buildTranslations() {
  const locs = installedLanguages.value
  const out = {}
  for (const loc of locs) {
    const t = form.translations[loc]
    out[loc] = {
      name: t?.name ?? '',
      description: t?.description ?? null,
    }
    if (out[loc].description === '') out[loc].description = null
  }
  return out
}

function validate() {
  const err = {}
  const defaultLoc = restaurant.value?.default_locale
  if (defaultLoc && form.translations[defaultLoc]) {
    const name = (form.translations[defaultLoc].name ?? '').trim()
    if (!name) err[`translations.${defaultLoc}.name`] = 'Name is required for the default language.'
  }
  fieldErrors.value = err
  return Object.keys(err).length === 0
}

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = {}
  if (!validate()) return
  saving.value = true
  try {
    const payload = { sort_order: form.sort_order, translations: buildTranslations() }
    if (isEdit.value) {
      await restaurantService.updateMenuItem(uuid.value, itemUuid.value, payload)
      toastStore.success('Menu item updated.')
      router.push({ name: 'RestaurantMenuItems', params: { uuid: uuid.value } })
    } else {
      const res = await restaurantService.createMenuItem(uuid.value, payload)
      toastStore.success('Menu item created.')
      router.push({ name: 'RestaurantMenuItems', params: { uuid: uuid.value } })
    }
  } catch (e) {
    const errs = getValidationErrors(e)
    if (Object.keys(errs).length > 0) fieldErrors.value = errs
    error.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    saving.value = false
  }
}

async function translateLocale(targetLoc) {
  const defaultLoc = restaurant.value?.default_locale
  if (!defaultLoc || targetLoc === defaultLoc) return
  const name = (form.translations[defaultLoc]?.name ?? '').trim()
  const desc = (form.translations[defaultLoc]?.description ?? '').trim()
  if (!name && !desc) {
    toastStore.error('Fill in name or description in the default language first.')
    return
  }
  translatingLocale.value = targetLoc
  error.value = ''
  try {
    if (name) {
      const resName = await localeService.translate({ text: name, from_locale: defaultLoc, to_locale: targetLoc })
      if (resName.translated_text != null) {
        if (!form.translations[targetLoc]) form.translations[targetLoc] = { name: '', description: null }
        form.translations[targetLoc].name = resName.translated_text
      }
    }
    if (desc) {
      const resDesc = await localeService.translate({ text: desc, from_locale: defaultLoc, to_locale: targetLoc })
      if (resDesc.translated_text != null) {
        if (!form.translations[targetLoc]) form.translations[targetLoc] = { name: '', description: null }
        form.translations[targetLoc].description = resDesc.translated_text
      }
    }
    toastStore.success('Translation applied. Review and save.')
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    translatingLocale.value = null
  }
}

async function loadRestaurant() {
  if (!uuid.value) return
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res.data ?? null
    breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
    if (restaurant.value && !isEdit.value) initFormFromRestaurant()
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
  } finally {
    loading.value = false
  }
}

function initFormFromRestaurant() {
  const def = restaurant.value?.default_locale
  form.sort_order = 0
  form.translations = {}
  if (def) form.translations[def] = { name: '', description: null }
}

async function loadMenuItem() {
  if (!uuid.value || !itemUuid.value) return
  try {
    const res = await restaurantService.getMenuItem(uuid.value, itemUuid.value)
    const item = res.data
    if (!item) return
    form.sort_order = item.sort_order ?? 0
    const trans = item.translations ?? {}
    const locs = restaurant.value?.languages ?? []
    form.translations = {}
    for (const loc of locs) {
      const t = trans[loc]
      form.translations[loc] = {
        name: t?.name ?? '',
        description: t?.description ?? null,
      }
    }
    const defLoc = restaurant.value?.default_locale
    const defaultName = defLoc ? (form.translations[defLoc]?.name ?? '') : ''
    breadcrumbStore.setMenuItemName(defaultName.trim() || null)
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
  }
}

onMounted(async () => {
  await loadRestaurant()
  if (isEdit.value && restaurant.value && itemUuid.value) await loadMenuItem()
})

watch([uuid, itemUuid], async () => {
  if (isEdit.value && uuid.value && itemUuid.value && restaurant.value) {
    await loadMenuItem()
  }
})
</script>
