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
        <a class="text-sm font-medium min-h-[44px] flex items-center hover:text-t1-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2 rounded" href="#menu">Menu</a>
        <a class="text-sm font-medium min-h-[44px] flex items-center hover:text-t1-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2 rounded" href="#reviews">Reviews</a>
        <a class="text-sm font-medium min-h-[44px] flex items-center hover:text-t1-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2 rounded" href="#contact">Contact</a>
        <button type="button" class="bg-t1-primary text-white px-6 py-2.5 rounded text-sm font-bold tracking-wide min-h-[44px] flex items-center hover:bg-t1-primary/90 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-t1-primary focus-visible:ring-offset-2" disabled>
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
