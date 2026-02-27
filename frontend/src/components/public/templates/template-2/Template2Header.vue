<template>
  <header
    class="sticky top-0 z-50 w-full bg-white border-b-2 border-charcoal-blue"
    role="banner"
  >
    <div class="max-w-[1400px] mx-auto h-20 flex items-center justify-between px-6">
      <div class="flex items-center gap-3">
        <span class="heading-utilitarian text-3xl font-extrabold tracking-tight text-charcoal-blue">{{ name || 'Restaurant' }}</span>
      </div>
      <nav class="hidden md:flex items-center gap-1">
        <a class="heading-utilitarian text-sm px-6 py-3 min-h-[44px] flex items-center hover:bg-concrete-gray transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2 rounded" href="#menu">01. Menu</a>
        <a class="heading-utilitarian text-sm px-6 py-3 min-h-[44px] flex items-center hover:bg-concrete-gray transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2 rounded" href="#reviews">02. Reviews</a>
        <a class="heading-utilitarian text-sm px-6 py-3 min-h-[44px] flex items-center hover:bg-concrete-gray transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2 rounded" href="#contact">03. Contact</a>
        <button type="button" class="ml-4 bg-oxidized-copper text-white px-8 py-3 min-h-[44px] flex items-center heading-utilitarian text-sm font-bold border-2 border-charcoal-blue hover:translate-x-1 hover:translate-y-1 hover:shadow-none shadow-[4px_4px_0px_0px_rgba(30,41,59,1)] transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-oxidized-copper focus-visible:ring-offset-2 rounded" disabled>
          [to be implemented]
        </button>
        <LanguageDropdown
          :languages="languages"
          :current-locale="currentLocale"
          class="rms-language-dropdown--t2 text-charcoal-blue border-charcoal-blue"
          @select-locale="$emit('select-locale', $event)"
        />
      </nav>
      <div class="flex items-center gap-2 md:hidden">
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
  </header>
</template>

<script setup>
import LanguageDropdown from '@/components/public/LanguageDropdown.vue'

defineProps({
  name: { type: String, default: '' },
  logoUrl: { type: String, default: '' },
  languages: { type: Array, default: () => [] },
  currentLocale: { type: String, default: '' },
})

defineEmits(['select-locale'])
</script>
