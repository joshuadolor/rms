<template>
  <section v-if="text" class="rms-about" id="about" aria-labelledby="about-heading">
    <h3 id="about-heading" class="rms-about__title">{{ aboutTitle }}</h3>
    <p class="rms-about__text">{{ text }}</p>
  </section>
</template>

<script setup>
defineProps({
  aboutTitle: { type: String, default: 'About' },
  text: { type: String, default: '' },
})
</script>
