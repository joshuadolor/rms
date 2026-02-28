<template>
  <section class="max-w-6xl mx-auto px-6 py-8" aria-label="Hero">
    <div class="relative w-full aspect-[21/9] overflow-hidden rounded-lg">
      <img
        v-if="bannerUrl"
        :src="bannerUrl"
        :alt="name ? `${name} — restaurant banner` : 'Restaurant banner'"
        class="w-full h-full object-cover"
        loading="eager"
      />
      <div v-else class="w-full h-full bg-t1-neutral-muted flex items-center justify-center text-t1-neutral-dark/60 text-sm">
        {{ showOwnerPlaceholder ? $t('public.addBannerInAdmin') : '' }}
      </div>
      <div class="absolute inset-0 bg-black/30 flex items-end p-8 md:p-12">
        <div class="max-w-2xl text-white">
          <div v-if="logoUrl" class="mb-4" data-testid="public-hero-logo">
            <div class="border-4 border-t1-neutral-light rounded-lg p-2 inline-block bg-white/60">
              <img
                :src="logoUrl"
                :alt="name || 'Restaurant'"
                class="w-64 rounded-lg object-cover"
              />
            </div>
          </div>
          <h1 class="text-4xl md:text-6xl font-bold mb-4">{{ name || 'Restaurant' }}</h1>
          <p v-if="tagline" class="text-lg md:text-xl font-light opacity-90">{{ tagline }}</p>
          <p v-if="description && !tagline" class="text-lg md:text-xl font-light opacity-90 line-clamp-3">{{ description }}</p>
          <p v-else-if="description" class="mt-2 text-base md:text-lg font-light opacity-85 line-clamp-2">{{ description }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  name: { type: String, default: '' },
  tagline: { type: String, default: '' },
  description: { type: String, default: '' },
  logoUrl: { type: String, default: '' },
  bannerUrl: { type: String, default: '' },
  /** When true and there is no banner, show "Add a cover image in Settings". When false (e.g. guest), show nothing. */
  showOwnerPlaceholder: { type: Boolean, default: false },
})
</script>
