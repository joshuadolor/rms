<template>
  <section class="rms-hero" :aria-label="name">
    <img
      v-if="bannerUrl"
      :src="bannerUrl"
      alt=""
      class="rms-hero__banner"
      width="1200"
      height="400"
      loading="lazy"
    />
    <div class="rms-hero__inner">
      <img
        v-if="logoUrl"
        :src="logoUrl"
        :alt="name"
        class="rms-hero__logo"
        width="80"
        height="80"
        loading="lazy"
        data-testid="public-hero-logo"
      />
      <h2 class="rms-hero__name">{{ name }}</h2>
      <p v-if="tagline" class="rms-hero__tagline">{{ tagline }}</p>
    </div>
  </section>
</template>

<script setup>
defineProps({
  name: { type: String, default: '' },
  tagline: { type: String, default: '' },
  logoUrl: { type: String, default: '' },
  bannerUrl: { type: String, default: '' },
})
</script>
