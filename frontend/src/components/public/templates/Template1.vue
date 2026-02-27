<template>
  <div
    data-testid="public-template-1"
    class="bg-white text-t1-neutral-dark antialiased font-font-display rms-template-1-root"
    :style="wrapperStyle"
  >
    <Template1Header
      :name="restaurant.name"
      :logo-url="restaurant.logo_url"
      :languages="languages"
      :current-locale="currentLocale"
      @select-locale="$emit('select-locale', $event)"
    />
    <main>
      <Template1Hero
        :name="restaurant.name"
        :tagline="restaurant.tagline"
        :logo-url="restaurant.logo_url || ''"
        :banner-url="restaurant.banner_url"
      />
      <Template1Menu
        :menu-groups="restaurant.menu_groups || []"
        :menu-items="restaurant.menu_items || []"
        :currency="restaurant.currency || 'USD'"
        :primary-color="restaurant.primary_color"
        @select-item="onSelectMenuItem"
      />
      <PublicMenuItemDetailModal
        v-model="detailOpen"
        :item="detailItem"
        :category-name="detailCategoryName"
        :primary-color="restaurant.primary_color"
        :currency="restaurant.currency || 'USD'"
        @update:model-value="onDetailClose"
      />
      <section id="reviews" class="rms-reviews-and-feedback" aria-labelledby="reviews-section-heading">
        <div class="rms-reviews-and-feedback__inner max-w-6xl mx-auto px-6 py-12 md:py-16">
          <h2 id="reviews-section-heading" class="rms-reviews-and-feedback__title text-2xl font-bold text-t1-neutral-dark mb-8 md:mb-10">
            Reviews & feedback
          </h2>
          <div class="rms-reviews-feedback-grid grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-[2rem]">
            <div class="rms-reviews-feedback-grid__reviews min-w-0">
              <Template1Reviews
                :feedbacks="restaurant.feedbacks || []"
                :primary-color="restaurant.primary_color"
              />
            </div>
            <div class="rms-reviews-feedback-grid__form min-w-0">
              <slot name="feedback-form" />
            </div>
          </div>
        </div>
      </section>
      <Template1About
        :text="(restaurant.description || '').trim()"
      />
      <Template1Contact
        :contacts="restaurant.contacts || []"
        :operating-hours="restaurant.operating_hours"
      />
      <Template1Map />
    </main>
    <Template1Footer
      :restaurant-name="restaurant.name"
      :logo-url="restaurant.logo_url"
    />
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import Template1Header from '@/components/public/templates/template-1/Template1Header.vue'
import Template1Hero from '@/components/public/templates/template-1/Template1Hero.vue'
import Template1Menu from '@/components/public/templates/template-1/Template1Menu.vue'
import Template1Reviews from '@/components/public/templates/template-1/Template1Reviews.vue'
import Template1About from '@/components/public/templates/template-1/Template1About.vue'
import Template1Contact from '@/components/public/templates/template-1/Template1Contact.vue'
import Template1Map from '@/components/public/templates/template-1/Template1Map.vue'
import Template1Footer from '@/components/public/templates/template-1/Template1Footer.vue'
import PublicMenuItemDetailModal from '@/components/public/PublicMenuItemDetailModal.vue'

const props = defineProps({
  restaurant: { type: Object, required: true },
  languages: { type: Array, default: () => [] },
  currentLocale: { type: String, default: '' },
})

defineEmits(['select-locale'])

const detailItem = ref(null)
const detailCategoryName = ref('Menu')
const detailOpen = ref(false)
let detailTriggerEl = null

function onSelectMenuItem(payload) {
  detailItem.value = payload?.item ?? null
  detailCategoryName.value = payload?.categoryName ?? 'Menu'
  detailTriggerEl = payload?.trigger ?? null
  detailOpen.value = true
}

function onDetailClose(open) {
  if (!open) {
    detailItem.value = null
    detailCategoryName.value = 'Menu'
    nextTick(() => {
      if (detailTriggerEl && typeof detailTriggerEl.focus === 'function') {
        detailTriggerEl.focus()
      }
      detailTriggerEl = null
    })
  }
}

const wrapperStyle = computed(() => ({
  '--rms-public-primary': props.restaurant?.primary_color || '#1152d4',
  '--t1-primary': props.restaurant?.primary_color || '#1152d4',
  '--t1-text': '#0f172a',
  '--t1-text-muted': '#64748b',
  '--t1-border': '#dbdfe6',
  '--t1-bg': '#f6f6f8',
  '--t1-bg-elevated': '#ffffff',
  '--t1-input-bg': '#ffffff',
  '--t1-radius': '8px',
  '--t1-radius-lg': '14px',
  '--t1-error': '#dc2626',
  '--t1-success': '#16a34a',
}))
</script>
