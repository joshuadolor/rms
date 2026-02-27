<template>
  <section class="rms-menu max-w-6xl mx-auto px-6 py-20" id="menu" aria-labelledby="menu-heading-t1">
    <div class="text-center mb-16">
      <h2 id="menu-heading-t1" class="text-3xl font-bold tracking-tight mb-2 text-t1-neutral-dark">Our Menu</h2>
      <div class="w-12 h-1 mx-auto rounded" :style="primaryBarStyle"></div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-12">
      <div v-for="(group, gIdx) in displayGroups" :key="gIdx" class="flex flex-col">
        <img
          v-if="group.image_url"
          :src="group.image_url"
          :alt="group.category_name || 'Category'"
          loading="lazy"
          class="w-full aspect-square object-cover rounded-lg mb-4 max-h-[140px] md:max-h-[180px]"
        />
        <h3 class="text-sm font-bold uppercase tracking-[0.2em] text-t1-neutral-muted mb-8 border-b border-t1-border pb-2">
          <span :style="getAvailabilityDisplay(group.availability).isUnavailableNow(now) ? { opacity: 0.8 } : undefined">{{ group.category_name }}</span>
          <span v-if="getAvailabilityDisplay(group.availability).label(now)" class="block text-xs font-normal normal-case tracking-normal mt-1">{{ getAvailabilityDisplay(group.availability).label(now) }}</span>
        </h3>
        <div class="space-y-8">
          <button
            v-for="item in group.items"
            :key="item.uuid"
            type="button"
            data-testid="public-menu-item"
            class="rms-menu-item-card flex flex-col bg-white rounded-xl py-5 px-6 w-full text-left"
            @click="onItemClick(item, group.category_name, $event)"
          >
            <div class="flex w-full items-center gap-4">
              <img
                v-if="item.image_url"
                :src="item.image_url"
                :alt="item.name || 'Untitled'"
                loading="lazy"
                class="w-20 h-20 md:w-24 md:h-24 shrink-0 aspect-square object-cover rounded-lg"
              />
              <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-4 w-full">
              <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                <span class="text-lg font-bold text-t1-neutral-dark" :style="itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined">{{ item.name || 'Untitled' }}</span>
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
              <span v-if="item.type !== 'with_variants' && item.is_available !== false && itemPrice(item) != null" class="text-lg font-bold whitespace-nowrap shrink-0" :style="[primaryTextStyle, itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined]">{{ formatPrice(itemPrice(item)) }}</span>
              <span v-else-if="item.type !== 'with_variants' && item.is_available === false" class="text-sm text-t1-neutral-muted whitespace-nowrap shrink-0">Not available</span>
            </div>
            <p v-if="formatAvailabilityForDisplay(item.availability, now)" class="text-t1-neutral-muted text-xs mt-0.5">{{ formatAvailabilityForDisplay(item.availability, now) }}</p>
            <p v-if="item.description" class="text-t1-neutral-muted text-sm mt-1">{{ item.description }}</p>
            <ul v-if="item.type === 'combo' && comboEntries(item).length" class="mt-2 list-none pl-4 md:pl-6 space-y-1 text-t1-neutral-muted text-xs border-l-2 border-t1-border ml-0.5" aria-label="Combo contents">
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
                  <span v-if="sku.price != null" class="text-sm font-bold" :style="[primaryTextStyle, itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined]">{{ formatPrice(sku.price) }}</span>
                  <img v-if="sku.image_url" :src="sku.image_url" :alt="variantSkuLabel(sku) || 'Variant'" loading="lazy" class="w-12 h-12 shrink-0 aspect-square object-cover rounded" />
                </li>
              </ul>
            </template>
              </div>
            </div>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { usePublicMenuDisplay } from '@/composables/usePublicMenuDisplay'
import { formatAvailabilityForDisplay, isAvailableNow } from '@/utils/availability'

const emit = defineEmits(['select-item'])

const props = defineProps({
  menuGroups: { type: Array, default: () => [] },
  menuItems: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
  primaryColor: { type: String, default: '' },
})

function onItemClick(item, categoryName, event) {
  emit('select-item', {
    item,
    categoryName: categoryName || 'Menu',
    trigger: event?.currentTarget ?? null,
  })
}

const {
  displayGroups,
  getAvailabilityDisplay,
  formatPrice,
  itemPrice,
  itemTags,
  comboEntries,
  variantOptionGroupsSummary,
  variantSkus,
  variantSkuLabel,
} = usePublicMenuDisplay(props)

const now = ref(new Date())
let nowInterval
onMounted(() => {
  nowInterval = setInterval(() => { now.value = new Date() }, 60 * 1000)
})
onUnmounted(() => {
  if (nowInterval) clearInterval(nowInterval)
})

function categoryUnavailableNow(availability) {
  return availability != null && typeof availability === 'object' && !isAvailableNow(availability, now.value)
}
function itemUnavailableNow(availability) {
  return availability != null && typeof availability === 'object' && !isAvailableNow(availability, now.value)
}

const primaryBarStyle = computed(() => ({ backgroundColor: props.primaryColor || '#1152d4' }))
const primaryTextStyle = computed(() => ({ color: props.primaryColor || '#1152d4' }))
</script>

<style scoped>
.rms-menu-item-card {
  cursor: pointer;
  border: none;
  transition: box-shadow 0.2s, transform 0.15s;
}

.rms-menu-item-card:focus-visible {
  outline: 2px solid var(--t1-primary, #1152d4);
  outline-offset: 2px;
}
@media (min-width: 768px) {
  .rms-menu-item-card {
    min-height: 44px;
  }
}
</style>
