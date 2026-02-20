<template>
  <Teleport v-if="open" to="body">
    <div
      ref="overlayRef"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      :aria-describedby="descriptionId"
      data-testid="app-modal-overlay"
      @keydown="onOverlayKeydown"
    >
      <div
        class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        aria-hidden="true"
        data-testid="app-modal-backdrop"
        @click="onClose"
      />
      <div :id="descriptionId" class="sr-only">{{ description }}</div>
      <div :id="titleId" class="sr-only">{{ title }}</div>
      <div
        ref="dialogRef"
        class="relative w-full max-h-[90dvh] overflow-hidden flex flex-col bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-md sm:max-w-lg"
        data-testid="app-modal-dialog"
        @click.stop
      >
        <div class="flex items-center justify-between shrink-0 px-4 py-3 border-b border-slate-200 dark:border-slate-800">
          <h2 class="text-lg font-bold text-charcoal dark:text-white">{{ title }}</h2>
          <button
            type="button"
            class="p-2 rounded-full text-slate-500 hover:text-charcoal dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
            title="Close"
            aria-label="Close"
            data-testid="app-modal-close"
            @click="onClose"
          >
            <span class="material-icons">close</span>
          </button>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-4">
          <slot />
        </div>
        <div v-if="$slots.footer" class="shrink-0 px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex gap-3 justify-end">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const overlayRef = ref(null)
const dialogRef = ref(null)
let previousActiveElement = null

const titleId = computed(() => `modal-title-${Math.random().toString(36).slice(2, 9)}`)
const descriptionId = computed(() => `modal-desc-${Math.random().toString(36).slice(2, 9)}`)

function getFocusables(container) {
  if (!container) return []
  const sel = 'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
  return Array.from(container.querySelectorAll(sel))
}

function onClose() {
  emit('close')
}

function onOverlayKeydown(e) {
  if (e.key === 'Escape') {
    onClose()
    return
  }
  if (e.key !== 'Tab' || !dialogRef.value) return
  const focusables = getFocusables(dialogRef.value)
  if (focusables.length === 0) return
  const current = document.activeElement
  const idx = focusables.indexOf(current)
  if (e.shiftKey) {
    if (idx <= 0) {
      e.preventDefault()
      focusables[focusables.length - 1].focus()
    }
  } else {
    if (idx === -1 || idx === focusables.length - 1) {
      e.preventDefault()
      focusables[0].focus()
    }
  }
}

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    document.body.style.overflow = 'hidden'
    previousActiveElement = document.activeElement
    nextTick(() => {
      const focusables = getFocusables(dialogRef.value)
      if (focusables.length > 0) focusables[0].focus()
    })
  } else {
    document.body.style.overflow = ''
    nextTick(() => {
      if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
        previousActiveElement.focus()
      }
    })
  }
})
</script>
