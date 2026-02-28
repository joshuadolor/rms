<template>
  <section
    v-if="text"
    id="about"
    class="max-w-[1400px] mx-auto px-6 py-12 border-b border-charcoal-blue"
    aria-labelledby="about-heading-t2"
  >
    <h2 id="about-heading-t2" class="heading-utilitarian text-5xl font-extrabold mb-8 text-charcoal-blue">{{ $t('public.about') }}</h2>
    <p class="text-charcoal-blue/80 whitespace-pre-line font-body">{{ text }}</p>
  </section>
</template>

<script setup>
defineProps({
  text: { type: String, default: '' },
})
</script>
