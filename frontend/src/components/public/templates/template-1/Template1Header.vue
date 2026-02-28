<template>
  <header
    class="sticky top-0 z-50 w-full bg-white/95 backdrop-blur-sm border-b border-t1-border"
    role="banner"
  >
    <div class="max-w-6xl mx-auto px-6 h-20 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-xl font-bold tracking-tight uppercase text-t1-neutral-dark">{{ name || 'Restaurant' }}</span>
      </div>
      <nav class="hidden md:flex items-center gap-10">
        <a class="text-sm font-medium min-h-[44px] flex items-center hover:text-t1-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2 rounded" href="#menu">{{ $t('app.menu') }}</a>
        <a class="text-sm font-medium min-h-[44px] flex items-center hover:text-t1-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2 rounded" href="#reviews">{{ $t('public.reviewsNav') }}</a>
        <a class="text-sm font-medium min-h-[44px] flex items-center hover:text-t1-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2 rounded" href="#contact">{{ $t('public.contactNav') }}</a>
        <button
          v-if="!isOwnerViewer"
          type="button"
          class="bg-t1-primary text-white px-6 py-2.5 rounded text-sm font-bold tracking-wide min-h-[44px] flex items-center hover:bg-t1-primary/90 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2"
          disabled
        >
          [to be implemented]
        </button>
        <LanguageDropdown
          :languages="languages"
          :current-locale="currentLocale"
          class="text-t1-neutral-dark border-t1-border"
          @select-locale="$emit('select-locale', $event)"
        />
      </nav>
      <div class="flex items-center gap-2 md:hidden">
        <LanguageDropdown
          :languages="languages"
          :current-locale="currentLocale"
          class="rms-language-dropdown--header-mobile text-t1-neutral-dark border-t1-border"
          @select-locale="$emit('select-locale', $event)"
        />
        <button type="button" class="p-2 min-h-[44px] min-w-[44px] flex items-center justify-center rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2" aria-label="Menu">
          <span class="material-symbols-outlined text-t1-neutral-dark">menu</span>
        </button>
      </div>
    </div>
    <div
      v-if="isOwnerViewer"
      class="border-t border-t1-border bg-t1-bg"
      role="status"
      aria-live="polite"
    >
      <div class="max-w-6xl mx-auto px-6 py-3 text-sm text-t1-neutral-dark">
        <p class="font-semibold">Owner notice</p>
        <p class="mt-1">Your restaurant needs more data. Please login and update it on the admin page.</p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
          <a
            v-if="ownerAdminUrl"
            :href="ownerAdminUrl"
            class="font-semibold underline underline-offset-2 inline-flex items-center min-h-[44px]"
            aria-label="Open admin page to update your restaurant data"
          >
            Open admin page
          </a>
          <p
            v-else
            class="text-xs text-t1-neutral-dark/80"
          >
            Admin link unavailable. Please sign in to the admin page from the main app.
          </p>
        </div>
        <p class="mt-2 text-xs text-t1-neutral-dark/80">This message is only shown to you the owner.</p>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import LanguageDropdown from '@/components/public/LanguageDropdown.vue'

const props = defineProps({
  name: { type: String, default: '' },
  logoUrl: { type: String, default: '' },
  viewer: { type: Object, default: () => ({ is_owner: false, owner_admin_url: null }) },
  languages: { type: Array, default: () => [] },
  currentLocale: { type: String, default: '' },
})

const isOwnerViewer = computed(() => props.viewer?.is_owner === true)
const ownerAdminUrl = computed(() => {
  const url = props.viewer?.owner_admin_url
  return typeof url === 'string' && url.trim() !== '' ? url : null
})

defineEmits(['select-locale'])
</script>
