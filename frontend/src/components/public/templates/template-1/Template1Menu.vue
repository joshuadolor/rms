<template>
  <section class="rms-menu max-w-6xl mx-auto px-6 py-20" id="menu" aria-labelledby="menu-heading-t1">
    <div class="text-center mb-16">
      <h2 id="menu-heading-t1" class="text-3xl font-bold tracking-tight mb-2 text-t1-neutral-dark">Our Menu</h2>
      <div class="w-12 h-1 mx-auto rounded" :style="primaryBarStyle"></div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-12">
      <div v-for="(group, gIdx) in displayGroups" :key="gIdx" class="flex flex-col">
        <h3 class="text-sm font-bold uppercase tracking-[0.2em] text-t1-neutral-muted mb-8 border-b border-t1-border pb-2">
          {{ group.category_name }}
          <span v-if="formatAvailabilityForDisplay(group.availability)" class="block text-xs font-normal normal-case tracking-normal mt-1">{{ formatAvailabilityForDisplay(group.availability) }}</span>
        </h3>
        <div class="space-y-8">
          <div
            v-for="item in group.items"
            :key="item.uuid"
            class="rms-menu-item flex flex-col"
          >
            <div class="flex items-center justify-between">
              <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                <span class="text-lg font-bold text-t1-neutral-dark">{{ item.name || 'Untitled' }}</span>
                <span
                  v-for="tag in itemTags(item)"
                  :key="tag.uuid"
                  class="inline-flex items-center gap-1 rounded-md border border-t1-border px-1.5 py-0.5 bg-t1-neutral-muted/15 min-h-[24px]"
                  :title="tag.text"
                  :aria-label="tag.text"
                  tabindex="0"
                >
                  <span class="material-symbols-outlined text-base shrink-0" :style="{ color: tag.color || 'currentColor' }" aria-hidden="true">{{ tag.icon || 'label' }}</span>
                  <span v-if="tag.text" class="text-sm">{{ tag.text }}</span>
                </span>
              </div>
              <div class="menu-dots shrink-0" aria-hidden="true"></div>
              <span v-if="item.type !== 'with_variants' && item.is_available !== false && itemPrice(item) != null" class="text-lg font-bold whitespace-nowrap shrink-0" :style="primaryTextStyle">{{ formatPrice(itemPrice(item)) }}</span>
              <span v-else-if="item.type !== 'with_variants' && item.is_available === false" class="text-sm text-t1-neutral-muted whitespace-nowrap shrink-0">Not available</span>
              <span v-else-if="item.type !== 'with_variants'" class="text-sm text-t1-neutral-muted whitespace-nowrap shrink-0">Price on request</span>
            </div>
            <p v-if="formatAvailabilityForDisplay(item.availability)" class="text-t1-neutral-muted text-xs mt-0.5">{{ formatAvailabilityForDisplay(item.availability) }}</p>
            <p v-if="item.description" class="text-t1-neutral-muted text-sm mt-1">{{ item.description }}</p>
            <ul v-if="item.type === 'combo' && comboEntries(item).length" class="mt-2 list-none pl-0 space-y-1 text-t1-neutral-muted text-sm" aria-label="Combo contents">
              <li v-for="(entry, eIdx) in comboEntries(item)" :key="eIdx">
                {{ entry.name }} × {{ entry.quantity }}{{ entry.modifier_label ? ` (${entry.modifier_label})` : '' }}
              </li>
            </ul>
            <template v-if="item.type === 'with_variants'">
              <p v-if="variantOptionGroupsSummary(item)" class="text-t1-neutral-muted text-sm mt-2">{{ variantOptionGroupsSummary(item) }}</p>
              <ul class="mt-2 list-none pl-0 space-y-1.5" aria-label="Size and price options">
                <li
                  v-for="sku in variantSkus(item)"
                  :key="sku.uuid"
                  class="flex flex-wrap items-center gap-x-2 gap-y-1 min-h-[44px]"
                >
                  <span class="text-t1-neutral-dark text-sm font-medium">{{ variantSkuLabel(sku) }}</span>
                  <span v-if="sku.price != null" class="text-sm font-bold" :style="primaryTextStyle">{{ formatPrice(sku.price) }}</span>
                  <img v-if="sku.image_url" :src="sku.image_url" :alt="variantSkuLabel(sku)" class="w-12 h-12 object-cover rounded" />
                </li>
              </ul>
            </template>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { usePublicMenuDisplay } from '@/composables/usePublicMenuDisplay'
import { formatAvailabilityForDisplay } from '@/utils/availability'

const props = defineProps({
  menuGroups: { type: Array, default: () => [] },
  menuItems: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
  primaryColor: { type: String, default: '' },
})

const {
  displayGroups,
  formatPrice,
  itemPrice,
  itemTags,
  comboEntries,
  variantOptionGroupsSummary,
  variantSkus,
  variantSkuLabel,
} = usePublicMenuDisplay(props)

const primaryBarStyle = computed(() => ({ backgroundColor: props.primaryColor || '#1152d4' }))
const primaryTextStyle = computed(() => ({ color: props.primaryColor || '#1152d4' }))
</script>
