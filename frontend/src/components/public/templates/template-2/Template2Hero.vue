<template>
  <section class="border-b border-charcoal-blue" aria-label="Hero">
    <div class="max-w-[1400px] mx-auto grid grid-cols-1 md:grid-cols-12 min-h-[min(60vh,400px)] md:min-h-[700px]">
      <div class="md:col-span-7 relative border-r border-charcoal-blue overflow-hidden p-8 md:p-12 flex flex-col justify-center bg-concrete-gray">
        <div class="relative z-20">
          <div v-if="logoUrl" class="mb-6" data-testid="public-hero-logo">
            <img :src="logoUrl" :alt="name || 'Restaurant'" class="w-40 md:w-64 rounded border-charcoal-blue" />
          </div>
          <div class="inline-block bg-charcoal-blue text-white px-4 py-1 mb-6 heading-utilitarian text-sm font-bold">EST. {{ displayYear }}</div>
          <h1 class="heading-utilitarian text-7xl sm:text-8xl md:text-9xl lg:text-9xl font-extrabold mb-8 leading-[0.85] text-charcoal-blue">
            <template v-if="name">
              {{ name.split(' ').slice(0, -1).join(' ') }}
              <template v-if="name.split(' ').length > 1"> </template>
              <span class="text-oxidized-copper">{{ name.split(' ').at(-1) }}</span>
            </template>
          </h1>
          <div v-if="heroSubtext" class="max-w-md border-l-8 border-oxidized-copper pl-8 py-2">
            <p  class="text-lg font-body leading-relaxed text-charcoal-blue/80">
              {{ heroSubtext }}
            </p>
          </div>
        </div>
        <div class="absolute inset-0 opacity-5 pointer-events-none bg-[length:20px_20px]" style="background-image: radial-gradient(#1e293b 1px, transparent 1px);" aria-hidden="true" />
      </div>
      <div class="md:col-span-5 relative bg-charcoal-blue group overflow-hidden">
        <img
          v-if="bannerUrl"
          :src="bannerUrl"
          :alt="name ? `${name} — restaurant interior` : 'Restaurant interior'"
          class="w-full h-full object-cover opacity-80 group-hover:grayscale transition-all duration-700"
          loading="eager"
        />
        <div v-else class="w-full h-full flex items-center justify-center text-charcoal-blue/40 text-sm">
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: { type: String, default: '' },
  tagline: { type: String, default: '' },
  description: { type: String, default: '' },
  logoUrl: { type: String, default: '' },
  bannerUrl: { type: String, default: '' },
  yearEstablished: { type: Number, default: null },
})

const currentYear = computed(() => new Date().getFullYear())
const displayYear = computed(() => props.yearEstablished ?? currentYear.value)
/** Restaurant tagline if set, otherwise restaurant description. */
const heroSubtext = computed(() => (props.tagline || props.description || '').trim() || '')
</script>
