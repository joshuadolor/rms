<template>
  <section class="rms-menu bg-pale-stone" id="menu" aria-labelledby="menu-heading-t2">
    <div class="max-w-[1400px] mx-auto">
      <div class="grid grid-cols-1 md:grid-cols-12 border-x border-charcoal-blue">
        <div class="md:col-span-12 p-12 md:p-20 border-b border-charcoal-blue flex flex-col md:flex-row justify-between items-end gap-8">
          <div>
            <h2 id="menu-heading-t2" class="heading-utilitarian text-6xl md:text-8xl font-extrabold leading-none text-charcoal-blue">Our Menu</h2>
          </div>
          <div v-if="menuDescription" class="text-right hidden md:block">
            <p class="font-mono text-xs uppercase opacity-70 mb-2">{{ menuDescription }}</p>
            <p class="font-mono text-[10px] uppercase opacity-40">{{ menuDescription }}</p>
          </div>
        </div>
        <div
          v-for="(group, gIdx) in displayGroups"
          :key="gIdx"
          class="md:col-span-6 p-12 border-b md:border-b-0 border-r border-charcoal-blue bg-white"
          :class="{ 'md:border-r-0': gIdx === displayGroups.length - 1 }"
        >
          <img
            v-if="group.image_url"
            :src="group.image_url"
            :alt="group.category_name || 'Category'"
            loading="lazy"
            class="w-full aspect-square object-cover rounded mb-6 max-h-[140px] md:max-h-[180px]"
          />
          <h3 class="heading-utilitarian text-4xl font-extrabold mb-12 flex flex-col gap-1 text-charcoal-blue">
            <span class="flex items-center gap-4">
              <span class="bg-charcoal-blue text-white px-3 py-1 text-xl">{{ String(gIdx + 1).padStart(2, '0') }}</span>
              <span :style="categoryUnavailableNow(group.availability) ? { opacity: 0.8 } : undefined">{{ group.category_name }}</span>
              <div class="flex-grow border-t-2 border-dotted border-charcoal-blue/20"></div>
              <span class="material-symbols-outlined text-oxidized-copper">restaurant_menu</span>
            </span>
            <span v-if="formatAvailabilityForDisplay(group.availability, now)" class="text-sm font-mono font-medium uppercase tracking-wider text-charcoal-blue/70">{{ formatAvailabilityForDisplay(group.availability, now) }}</span>
          </h3>
          <div class="space-y-16">
            <div
              v-for="item in group.items"
              :key="item.uuid"
              data-testid="public-menu-item"
              class="group border-b border-concrete-gray pb-8 last:border-0"
            >
              <div class="flex items-start gap-4">
                <img
                  v-if="(item.type === 'simple' || item.type === 'combo') && item.image_url"
                  :src="item.image_url"
                  :alt="item.name || 'Untitled'"
                  loading="lazy"
                  class="w-20 h-20 md:w-24 md:h-24 shrink-0 aspect-square object-cover rounded"
                />
                <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between mb-3">
                <div class="flex flex-wrap items-center gap-2 min-w-0" :class="item.is_available === false ? 'text-charcoal-blue/60' : 'group-hover:[&_.heading-utilitarian]:text-oxidized-copper'">
                  <span class="heading-utilitarian text-3xl transition-colors" :style="itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined">{{ item.name || 'Untitled' }}</span>
                  <span
                    v-for="tag in itemTags(item)"
                    :key="tag.uuid"
                    class="inline-flex items-center gap-1 rounded border border-charcoal-blue/20 px-1.5 py-0.5 bg-charcoal-blue/5 min-h-[24px]"
                    :title="tag.text"
                    :aria-label="tag.text"
                    tabindex="0"
                  >
                    <span class="material-symbols-outlined text-lg shrink-0" :style="{ color: tag.color || 'currentColor' }" aria-hidden="true">{{ tag.icon || 'label' }}</span>
                    <span v-if="tag.text" class="font-mono text-xs uppercase">{{ tag.text }}</span>
                  </span>
                </div>
                <span v-if="item.type !== 'with_variants' && item.is_available !== false && itemPrice(item) != null" class="font-mono font-bold text-xl text-oxidized-copper shrink-0" :style="itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined">{{ formatPrice(itemPrice(item)) }}</span>
                <span v-else-if="item.type !== 'with_variants' && item.is_available === false" class="font-mono text-sm text-charcoal-blue/60 shrink-0">Not available</span>
              </div>
              <p v-if="formatAvailabilityForDisplay(item.availability, now)" class="text-xs font-mono uppercase tracking-wider text-charcoal-blue/60 mt-0.5">{{ formatAvailabilityForDisplay(item.availability, now) }}</p>
              <p v-if="item.description" class="text-sm text-charcoal-blue/60 font-medium uppercase tracking-wider">{{ item.description }}</p>
              <ul v-if="item.type === 'combo' && comboEntries(item).length" class="mt-2 list-none pl-4 md:pl-6 space-y-1 text-charcoal-blue/60 text-xs font-medium uppercase tracking-wider border-l-2 border-charcoal-blue/20 ml-0.5" aria-label="Combo contents">
                <li v-for="(entry, eIdx) in comboEntries(item)" :key="eIdx">
                  {{ entry.name }} × {{ entry.quantity }}{{ entry.modifier_label ? ` (${entry.modifier_label})` : '' }}
                </li>
              </ul>
              <template v-if="item.type === 'with_variants'">
                <p v-if="variantOptionGroupsSummary(item)" class="text-charcoal-blue/60 text-sm mt-2 font-medium uppercase tracking-wider">{{ variantOptionGroupsSummary(item) }}</p>
                <ul class="mt-2 list-none pl-0 space-y-2" aria-label="Size and price options">
                  <li
                    v-for="sku in variantSkus(item)"
                    :key="sku.uuid"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 min-h-[44px]"
                  >
                    <span class="text-charcoal-blue font-medium">{{ variantSkuLabel(sku) }}</span>
                    <span v-if="sku.price != null" class="font-mono font-bold text-oxidized-copper" :style="itemUnavailableNow(item.availability) ? { opacity: 0.8 } : undefined">{{ formatPrice(sku.price) }}</span>
                    <img v-if="sku.image_url" :src="sku.image_url" :alt="variantSkuLabel(sku)" loading="lazy" class="w-12 h-12 object-cover rounded" />
                  </li>
                </ul>
              </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { usePublicMenuDisplay } from '@/composables/usePublicMenuDisplay'
import { formatAvailabilityForDisplay, isAvailableNow } from '@/utils/availability'

const props = defineProps({
  menuGroups: { type: Array, default: () => [] },
  menuItems: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
  menuDescription: { type: String, default: '' },
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
</script>
