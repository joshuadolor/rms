<template>
  <div class="max-w-4xl" data-testid="superadmin-legal-page">
    <header class="mb-6 lg:mb-8">
      <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">{{ $t('app.legalContent') }}</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $t('app.legalContentSubtitle') }}</p>
    </header>

    <div v-if="loadError" class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm" role="alert">
      {{ loadError }}
    </div>

    <div v-else-if="loading" class="space-y-6">
      <div class="h-48 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 animate-pulse" />
      <div class="h-48 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-slate-800 animate-pulse" />
    </div>

    <form v-else class="space-y-6 lg:space-y-8" novalidate @submit.prevent="save">
      <!-- Locale tabs: mobile-first horizontal scroll / wrap -->
      <div class="border-b border-slate-200 dark:border-slate-800 -mx-4 px-4 overflow-x-auto lg:mx-0 lg:px-0">
        <nav class="flex gap-1 min-w-0" role="tablist" aria-label="Language">
          <button
            v-for="loc in LEGAL_LOCALES"
            :key="loc"
            type="button"
            role="tab"
            :aria-selected="activeLocale === loc"
            :aria-controls="`panel-${loc}`"
            :id="`tab-${loc}`"
            class="shrink-0 min-h-[44px] px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors"
            :class="activeLocale === loc
              ? 'border-primary text-primary bg-primary/5 dark:bg-primary/10'
              : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-charcoal dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800'"
            data-testid="legal-tab"
            @click="activeLocale = loc"
          >
            {{ localeLabel(loc) }}
          </button>
        </nav>
      </div>

      <!-- Active locale panel -->
      <div
        v-for="loc in LEGAL_LOCALES"
        :key="loc"
        :id="`panel-${loc}`"
        role="tabpanel"
        :aria-labelledby="`tab-${loc}`"
        :hidden="activeLocale !== loc"
        :dir="loc === 'ar' ? 'rtl' : 'ltr'"
        :lang="loc"
        class="space-y-6"
      >
        <section class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800">
            <h3 class="text-base font-semibold text-charcoal dark:text-white">{{ $t('app.termsOfService') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('app.legalHtmlHint') }}</p>
          </div>
          <div class="p-4">
            <textarea
              :value="localeData[loc].terms_of_service"
              class="w-full min-h-[200px] px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-zinc-800 text-charcoal dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary/30 focus:border-primary"
              :placeholder="$t('app.termsPlaceholder')"
              :aria-label="$t('app.termsOfService')"
              data-testid="legal-terms-input"
              @input="localeData[loc].terms_of_service = ($event.target).value"
            />
          </div>
        </section>

        <section class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-zinc-900 overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800">
            <h3 class="text-base font-semibold text-charcoal dark:text-white">{{ $t('app.privacyPolicy') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('app.legalHtmlHint') }}</p>
          </div>
          <div class="p-4">
            <textarea
              :value="localeData[loc].privacy_policy"
              class="w-full min-h-[200px] px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-zinc-800 text-charcoal dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary/30 focus:border-primary"
              :placeholder="$t('app.privacyPlaceholder')"
              :aria-label="$t('app.privacyPolicy')"
              data-testid="legal-privacy-input"
              @input="localeData[loc].privacy_policy = ($event.target).value"
            />
          </div>
        </section>
      </div>

      <div v-if="saveError" class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm" role="alert">
        {{ saveError }}
      </div>

      <div class="flex flex-wrap gap-3">
        <AppButton
          type="submit"
          variant="primary"
          :disabled="saving"
          class="min-h-[44px]"
        >
          <template v-if="saving" #icon>
            <span class="material-icons animate-spin text-lg" aria-hidden="true">sync</span>
          </template>
          {{ saving ? $t('app.saving') : $t('app.saveChanges') }}
        </AppButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import { useToastStore } from '@/stores/toast'
import { legalContentService } from '@/services'

const LEGAL_LOCALES = legalContentService.LEGAL_LOCALES

const { t } = useI18n()
const toastStore = useToastStore()

const activeLocale = ref('en')
const localeData = reactive({
  en: { terms_of_service: '', privacy_policy: '' },
  es: { terms_of_service: '', privacy_policy: '' },
  ar: { terms_of_service: '', privacy_policy: '' },
})

const loading = ref(true)
const saving = ref(false)
const loadError = ref('')
const saveError = ref('')

function localeLabel(loc) {
  const key = { en: 'localeEn', es: 'localeEs', ar: 'localeAr' }[loc]
  return key ? t(`app.${key}`) : loc
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const data = await legalContentService.getLegal()
    LEGAL_LOCALES.forEach((loc) => {
      localeData[loc].terms_of_service = data[loc]?.terms_of_service ?? ''
      localeData[loc].privacy_policy = data[loc]?.privacy_policy ?? ''
    })
  } catch (err) {
    loadError.value = legalContentService.normalizeApiError(err).message || t('app.errorLoading')
  } finally {
    loading.value = false
  }
}

async function save() {
  saveError.value = ''
  saving.value = true
  try {
    const payload = {
      en: { terms_of_service: localeData.en.terms_of_service, privacy_policy: localeData.en.privacy_policy },
      es: { terms_of_service: localeData.es.terms_of_service, privacy_policy: localeData.es.privacy_policy },
      ar: { terms_of_service: localeData.ar.terms_of_service, privacy_policy: localeData.ar.privacy_policy },
    }
    await legalContentService.updateLegal(payload)
    saveError.value = ''
    toastStore.success(t('app.legalContentUpdated') || 'Legal content updated.')
  } catch (err) {
    const msg = legalContentService.normalizeApiError(err).message || t('app.errorSaving')
    saveError.value = msg
    toastStore.error(msg)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
