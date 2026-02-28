<template>
  <header
    class="sticky top-0 z-50 w-full bg-white border-b-2 border-charcoal-blue"
    role="banner"
  >
    <div class="max-w-[1400px] mx-auto h-20 flex items-center justify-between px-6">
      <div class="flex min-w-0 flex-1 items-center gap-3 pr-3 md:pr-0">
        <span
          class="heading-utilitarian block max-w-full truncate text-2xl font-extrabold leading-tight tracking-tight text-charcoal-blue md:text-3xl"
          :title="name || 'Restaurant'"
        >
          {{ name || 'Restaurant' }}
        </span>
      </div>
      <nav class="hidden md:flex items-center gap-1">
        <a class="heading-utilitarian text-sm px-6 py-3 min-h-[44px] flex items-center hover:bg-concrete-gray transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2 rounded" href="#menu">01. Menu</a>
        <a class="heading-utilitarian text-sm px-6 py-3 min-h-[44px] flex items-center hover:bg-concrete-gray transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2 rounded" href="#reviews">02. Reviews</a>
        <a class="heading-utilitarian text-sm px-6 py-3 min-h-[44px] flex items-center hover:bg-concrete-gray transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2 rounded" href="#contact">03. Contact</a>
        <button
          v-if="!isOwnerViewer"
          type="button"
          class="ml-4 bg-oxidized-copper text-white px-8 py-3 min-h-[44px] flex items-center heading-utilitarian text-sm font-bold border-2 border-charcoal-blue hover:translate-x-1 hover:translate-y-1 hover:shadow-none shadow-[4px_4px_0px_0px_rgba(30,41,59,1)] transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2 rounded"
          disabled
        >
          [to be implemented]
        </button>
        <LanguageDropdown
          :languages="languages"
          :current-locale="currentLocale"
          class="rms-language-dropdown--t2 text-charcoal-blue border-charcoal-blue"
          @select-locale="$emit('select-locale', $event)"
        />
      </nav>
      <div class="flex shrink-0 items-center gap-2 md:hidden">
        <LanguageDropdown
          :languages="languages"
          :current-locale="currentLocale"
          class="rms-language-dropdown--t2 rms-language-dropdown--header-mobile text-charcoal-blue border-charcoal-blue"
          @select-locale="$emit('select-locale', $event)"
        />
        <button type="button" class="p-2 min-h-[44px] min-w-[44px] flex items-center justify-center text-charcoal-blue rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2" aria-label="Menu">
          <span class="material-symbols-outlined text-4xl">menu</span>
        </button>
      </div>
    </div>
    <div
      v-if="isOwnerViewer"
      class="border-t-2 border-charcoal-blue bg-concrete-gray"
      role="status"
      aria-live="polite"
    >
      <div class="max-w-[1400px] mx-auto px-6 py-3 text-sm text-charcoal-blue">
        <p class="heading-utilitarian text-xs font-bold uppercase tracking-wide">Owner notice</p>
        <p class="mt-1">Your restaurant needs more data. Please login and update it on the admin page.</p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
          <a
            v-if="ownerAdminUrl"
            :href="ownerAdminUrl"
            class="font-bold underline underline-offset-2 inline-flex items-center min-h-[44px] w-fit"
            aria-label="Open admin page to update your restaurant data"
          >
            Open admin page
          </a>
          <p
            v-else
            class="text-xs text-charcoal-blue/80"
          >
            Admin link unavailable. Please sign in to the admin page from the main app.
          </p>
        </div>
        <p class="mt-2 text-xs text-charcoal-blue/80">This message is only shown to you the owner.</p>
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
