<template>
  <div
    data-testid="public-template-2"
    class="bg-pale-stone text-charcoal-blue antialiased selection:bg-oxidized-copper selection:text-white font-body rms-template-2-root"
    :style="wrapperStyle"
  >
    <Template2Header
      :name="restaurant.name"
      :logo-url="restaurant.logo_url"
      :languages="languages"
      :current-locale="currentLocale"
      @select-locale="$emit('select-locale', $event)"
    />
    <main class="space-y-0">
      <Template2Hero
        :name="restaurant.name"
        :tagline="restaurant.tagline"
        :description="(restaurant.description || '').trim()"
        :logo-url="restaurant.logo_url || ''"
        :banner-url="restaurant.banner_url"
        :year-established="restaurant.year_established"
      />
      <Template2Menu
        :menu-groups="restaurant.menu_groups || []"
        :menu-items="restaurant.menu_items || []"
        :currency="restaurant.currency || 'USD'"
      />
      <section id="reviews" class="rms-reviews-and-feedback" aria-labelledby="reviews-section-heading">
        <div class="rms-reviews-and-feedback__inner max-w-[1400px] border-2 border-charcoal-blue mx-auto px-6 py-12 md:py-16">
          <h2 id="reviews-section-heading" class="rms-reviews-and-feedback__title heading-utilitarian text-3xl md:text-5xl font-extrabold uppercase tracking-wide text-charcoal-blue mb-8 md:mb-10">
            Reviews & feedback
          </h2>
          <div class="rms-reviews-and-feedback__box border-charcoal-blue bg-pale-stone">
            <div class="rms-reviews-feedback-grid grid grid-cols-1 md:grid-cols-[1fr_auto] gap-0">
              <div v-if="restaurant.feedbacks && restaurant.feedbacks.length" class="rms-reviews-feedback-grid__reviews min-w-0 p-6 md:p-8 md:border-charcoal-blue">
                <Template2Reviews
                  :feedbacks="restaurant.feedbacks || []"
                  :nested="true"
                />
              </div>
              <div class="rms-reviews-feedback-grid__form min-w-0 p-6 md:p-8 md:min-w-[500px]">
                <slot name="feedback-form" />
              </div>
            </div>
          </div>
        </div>
      </section>
      <Template2About
        :text="(restaurant.description || '').trim()"
      />
      <Template2Contact
        :contacts="restaurant.contacts || []"
        :operating-hours="restaurant.operating_hours"
      />
      <Template2Map />
    </main>
    <Template2Footer
      :restaurant-name="restaurant.name"
      :logo-url="restaurant.logo_url"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Template2Header from '@/components/public/templates/template-2/Template2Header.vue'
import Template2Hero from '@/components/public/templates/template-2/Template2Hero.vue'
import Template2Menu from '@/components/public/templates/template-2/Template2Menu.vue'
import Template2Reviews from '@/components/public/templates/template-2/Template2Reviews.vue'
import Template2About from '@/components/public/templates/template-2/Template2About.vue'
import Template2Contact from '@/components/public/templates/template-2/Template2Contact.vue'
import Template2Map from '@/components/public/templates/template-2/Template2Map.vue'
import Template2Footer from '@/components/public/templates/template-2/Template2Footer.vue'

const props = defineProps({
  restaurant: { type: Object, required: true },
  languages: { type: Array, default: () => [] },
  currentLocale: { type: String, default: '' },
})

defineEmits(['select-locale'])

const wrapperStyle = computed(() => ({
  '--t2-primary': '#B35C38',
  '--t2-text': '#1E293B',
  '--t2-border': '#1E293B',
  '--t2-bg': '#F3F4F6',
  '--t2-bg-elevated': '#ffffff',
  '--t2-radius': '4px',
  '--t2-error': '#dc2626',
  '--t2-success': '#16a34a',
}))
</script>
