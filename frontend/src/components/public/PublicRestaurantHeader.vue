<template>
  <header class="rms-header" role="banner">
    <div class="rms-header__inner">
      <img
        v-if="logoUrl"
        :src="logoUrl"
        :alt="name"
        class="rms-header__logo"
        width="56"
        height="56"
        loading="eager"
      />
      <div>
        <h1 class="rms-header__title">{{ name }}</h1>
        <p v-if="tagline" class="rms-header__tagline">{{ tagline }}</p>
      </div>
    </div>
  </header>
</template>

<script setup>
defineProps({
  name: { type: String, default: '' },
  tagline: { type: String, default: '' },
  logoUrl: { type: String, default: '' },
})
</script>
