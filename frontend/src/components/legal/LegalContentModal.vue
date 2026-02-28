<template>
  <Teleport v-if="open" to="body">
    <div
      ref="dialogRootRef"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      :aria-label="modalTitle"
      data-testid="legal-content-modal"
      tabindex="-1"
      @keydown.escape="onClose"
      @keydown.tab="trapFocus"
    >
      <div
        class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        aria-hidden="true"
        data-testid="legal-modal-backdrop"
        @click="onClose"
      />
      <div
        class="relative w-full max-h-[90dvh] overflow-hidden flex flex-col bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-2xl"
        @click.stop
      >
        <div class="flex items-center justify-between shrink-0 px-4 py-3 border-b border-slate-200 dark:border-slate-800">
          <h2 class="text-lg font-bold text-charcoal dark:text-white">{{ modalTitle }}</h2>
          <button
            ref="closeButtonRef"
            type="button"
            class="p-2 rounded-full text-slate-500 hover:text-charcoal dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
            :aria-label="$t('app.close')"
            data-testid="legal-modal-close"
            @click="onClose"
          >
            <span class="material-icons">close</span>
          </button>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-4 min-h-0">
          <p v-if="loading" class="text-slate-500 dark:text-slate-400">{{ $t('app.loading') || 'Loading…' }}</p>
          <div
            v-else-if="content"
            class="prose prose-slate dark:prose-invert max-w-none text-sm"
            data-testid="legal-content-body"
            :dir="props.locale === 'ar' ? 'rtl' : 'ltr'"
            :lang="props.locale === 'ar' ? 'ar' : undefined"
            v-html="content"
          />
          <p v-else class="text-slate-500 dark:text-slate-400">{{ $t('app.noContent') || 'No content yet.' }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed, nextTick, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { legalContentService } from '@/services'

const props = defineProps({
  open: { type: Boolean, default: false },
  /** 'terms' | 'privacy' */
  type: { type: String, default: 'terms' },
  /** App locale for content (en, es, ar). If not set, backend falls back to en. */
  locale: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const { t } = useI18n()

const dialogRootRef = ref(null)
const closeButtonRef = ref(null)
const previousActiveElement = ref(/** @type {HTMLElement | null} */ (null))

const loading = ref(false)
const content = ref('')

const modalTitle = computed(() =>
  props.type === 'privacy' ? t('app.privacyPolicy') : t('app.termsOfService')
)

function getFocusables() {
  const root = dialogRootRef.value
  if (!root) return []
  const selector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  return Array.from(root.querySelectorAll(selector))
}

function trapFocus(e) {
  if (e.key !== 'Tab') return
  const focusables = getFocusables()
  if (focusables.length === 0) return
  const first = focusables[0]
  const last = focusables[focusables.length - 1]
  if (e.shiftKey) {
    if (document.activeElement === first) {
      e.preventDefault()
      last.focus()
    }
  } else {
    if (document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

async function fetchContent() {
  if (!props.open) return
  loading.value = true
  content.value = ''
  const locale = props.locale || undefined
  try {
    const res = props.type === 'privacy'
      ? await legalContentService.getPrivacy(locale)
      : await legalContentService.getTerms(locale)
    content.value = res?.content ?? ''
  } catch {
    content.value = ''
  } finally {
    loading.value = false
  }
}

watch(() => [props.open, props.type, props.locale], () => {
  if (props.open) {
    previousActiveElement.value = document.activeElement instanceof HTMLElement ? document.activeElement : null
    fetchContent()
    nextTick(() => {
      closeButtonRef.value?.focus()
    })
  } else {
    content.value = ''
    nextTick(() => {
      if (previousActiveElement.value?.focus) {
        previousActiveElement.value.focus()
      }
      previousActiveElement.value = null
    })
  }
}, { immediate: true })

function onClose() {
  if (previousActiveElement.value?.focus) {
    previousActiveElement.value.focus()
  }
  previousActiveElement.value = null
  emit('close')
}

onBeforeUnmount(() => {
  if (previousActiveElement.value?.focus) {
    previousActiveElement.value.focus()
  }
})
</script>
