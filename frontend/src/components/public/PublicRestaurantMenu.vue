<template>
  <section class="rms-menu" id="menu" aria-labelledby="menu-heading">
    <h3 id="menu-heading" class="rms-menu__heading">{{ menuHeading }}</h3>
    <div v-for="(group, gIdx) in groups" :key="gIdx" class="rms-menu__group">
      <h4 class="rms-menu__category">{{ group.category_name }}</h4>
      <ul class="rms-menu__list">
        <li
          v-for="item in group.items"
          :key="item.uuid"
          class="rms-menu-item"
          :class="{ 'rms-menu-item--unavailable': item.is_available === false }"
        >
          <div class="rms-menu-item__row">
            <span class="rms-menu-item__name">
              {{ item.name || 'Untitled' }}
              <span v-if="item.is_available === false" class="rms-menu-item__unavailable-label">Not available</span>
              <span v-if="itemTags(item).length" class="rms-menu-item__tags" aria-hidden="true">
                <span
                  v-for="tag in itemTags(item)"
                  :key="tag.uuid"
                  class="rms-menu-item__tag"
                  :title="tag.text"
                  :style="tagStyle(tag)"
                >
                  <span class="material-symbols-outlined rms-menu-item__tag-icon" aria-hidden="true">{{ tag.icon || 'label' }}</span>
                </span>
              </span>
            </span>
            <span
              v-if="item.type !== 'with_variants' && item.is_available !== false && itemPrice(item) != null"
              class="rms-menu-item__price"
            >
              {{ formatPrice(itemPrice(item)) }}
            </span>
            <span v-else-if="item.type !== 'with_variants' && item.is_available === false" class="rms-menu-item__price rms-menu-item__price--unavailable">Not available</span>
            <span v-else-if="item.type !== 'with_variants'" class="rms-menu-item__price rms-menu-item__price--unavailable">Price on request</span>
          </div>
          <p v-if="item.description" class="rms-menu-item__description">
            {{ item.description }}
          </p>
          <ul v-if="item.type === 'combo' && comboEntries(item).length" class="rms-menu-item__combo-list" aria-label="Combo contents">
            <li v-for="(entry, eIdx) in comboEntries(item)" :key="eIdx">
              {{ entry.name }} × {{ entry.quantity }}{{ entry.modifier_label ? ` (${entry.modifier_label})` : '' }}
            </li>
          </ul>
          <template v-if="item.type === 'with_variants'">
            <p v-if="variantOptionGroupsSummary(item)" class="rms-menu-item__variant-summary">{{ variantOptionGroupsSummary(item) }}</p>
            <ul class="rms-menu-item__variant-skus" aria-label="Size and price options">
              <li v-for="sku in variantSkus(item)" :key="sku.uuid" class="rms-menu-item__variant-sku">
                <span>{{ variantSkuLabel(sku) }}</span>
                <span v-if="sku.price != null">{{ formatPrice(sku.price) }}</span>
                <img v-if="sku.image_url" :src="sku.image_url" :alt="variantSkuLabel(sku)" class="rms-menu-item__sku-img" />
              </li>
            </ul>
          </template>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  menuHeading: { type: String, default: 'Menu' },
  /** API returns flat menu_items; we show one group "Menu". Pass menuGroups if backend adds categories. */
  menuGroups: {
    type: Array,
    default: () => [],
  },
  /** Flat list from API; used to build a single group when menuGroups is empty */
  menuItems: {
    type: Array,
    default: () => [],
  },
  currency: { type: String, default: 'USD' },
})

const groups = computed(() => {
  if (props.menuGroups.length) return props.menuGroups
  if (!props.menuItems.length) return []
  return [
    {
      category_name: props.menuHeading,
      items: [...props.menuItems],
    },
  ]
})

function formatPrice(price) {
  return new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price) + ' ' + props.currency
}

function itemPrice(item) {
  if (!item) return null
  if (item.type === 'with_variants') return null
  return item.price != null ? Number(item.price) : null
}

function itemTags(item) {
  return Array.isArray(item?.tags) ? item.tags : []
}

function comboEntries(item) {
  return Array.isArray(item?.combo_entries) ? item.combo_entries : []
}

function variantOptionGroupsSummary(item) {
  const groups = Array.isArray(item?.variant_option_groups) ? item.variant_option_groups : []
  if (!groups.length) return ''
  return groups.map((g) => `${g.name || 'Option'}: ${Array.isArray(g.values) ? g.values.join(', ') : ''}`).join('; ')
}

function variantSkus(item) {
  return Array.isArray(item?.variant_skus) ? item.variant_skus : []
}

function variantSkuLabel(sku) {
  if (!sku?.option_values || typeof sku.option_values !== 'object') return ''
  const vals = Object.values(sku.option_values).filter(Boolean)
  return vals.join(', ') || '—'
}

function tagStyle(tag) {
  const color = tag?.color || '#64748b'
  return { color }
}
</script>

<style scoped>
.rms-menu-item__tags {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin-left: 0.35rem;
  vertical-align: middle;
}
.rms-menu-item__tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  min-height: 24px;
}
.rms-menu-item__tag-icon {
  font-size: 1rem;
}
.rms-menu-item__price--unavailable {
  font-size: 0.875rem;
  color: #64748b;
}
.rms-menu-item__combo-list,
.rms-menu-item__variant-skus {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 0;
}
.rms-menu-item__combo-list li,
.rms-menu-item__variant-sku {
  padding: 0.25rem 0;
  min-height: 44px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.rms-menu-item__variant-summary {
  margin: 0.5rem 0 0;
  font-size: 0.875rem;
  color: #64748b;
}
.rms-menu-item__sku-img {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 6px;
}
</style>
