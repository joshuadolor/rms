<template>
  <section class="rms-menu" id="menu" aria-labelledby="menu-heading">
    <h3 id="menu-heading" class="rms-menu__heading">{{ menuHeading }}</h3>
    <div v-for="(group, gIdx) in displayGroups" :key="gIdx" class="rms-menu__group">
      <div v-if="group.image_url" class="rms-menu__category-img-wrap">
        <img
          :src="group.image_url"
          :alt="group.category_name || 'Category'"
          loading="lazy"
          class="rms-menu__category-img"
        />
      </div>
      <h4 class="rms-menu__category">{{ group.category_name }}</h4>
      <ul class="rms-menu__list">
        <li
          v-for="item in group.items"
          :key="item.uuid"
          class="rms-menu-item"
          :class="{ 'rms-menu-item--unavailable': item.is_available === false }"
        >
          <button
            type="button"
            class="rms-menu-item__card"
            @click="onItemClick(item, group.category_name, $event)"
          >
            <div class="rms-menu-item__top">
            <img
              v-if="item.image_url"
              :src="item.image_url"
              :alt="item.name || 'Untitled'"
              loading="lazy"
              class="rms-menu-item__thumb"
            />
            <div class="rms-menu-item__content">
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
                <img v-if="sku.image_url" :src="sku.image_url" :alt="variantSkuLabel(sku) || 'Variant'" loading="lazy" class="rms-menu-item__sku-img" />
              </li>
            </ul>
          </template>
            </div>
          </div>
          </button>
        </li>
      </ul>
    </div>

    <PublicMenuItemDetailModal
      v-model="detailOpen"
      :item="detailItem"
      :category-name="detailCategoryName"
      :primary-color="primaryColor"
      :currency="currency"
      @update:model-value="onDetailClose"
    />
  </section>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { usePublicMenuDisplay } from '@/composables/usePublicMenuDisplay'
import PublicMenuItemDetailModal from '@/components/public/PublicMenuItemDetailModal.vue'

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

const detailItem = ref(null)
const detailCategoryName = ref('Menu')
const detailOpen = ref(false)
let detailTriggerEl = null

function onItemClick(item, categoryName, event) {
  detailItem.value = item
  detailCategoryName.value = categoryName || 'Menu'
  detailTriggerEl = event?.currentTarget ?? null
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

function tagStyle(tag) {
  const color = tag?.color || '#64748b'
  return { color }
}
</script>

<style scoped>
.rms-menu-item__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
}
.rms-menu-item__name {
  min-width: 0;
}
.rms-menu-item__price {
  flex-shrink: 0;
}
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
.rms-menu-item__combo-list {
  padding-left: 1rem;
  margin-left: 0.125rem;
  border-left: 2px solid #e2e8f0;
  font-size: 0.75rem;
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
.rms-menu-item__combo-list li {
  font-size: 0.75rem;
}
.rms-menu__category-img-wrap {
  aspect-ratio: 1;
  width: 100%;
  max-width: 140px;
  max-height: 140px;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 0.75rem;
}
@media (min-width: 768px) {
  .rms-menu__category-img-wrap {
    max-width: 180px;
    max-height: 180px;
  }
}
.rms-menu__category-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.rms-menu-item__top {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}
.rms-menu-item__thumb {
  width: 80px;
  height: 80px;
  flex-shrink: 0;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 8px;
}
@media (min-width: 768px) {
  .rms-menu-item__thumb {
    width: 96px;
    height: 96px;
  }
}
.rms-menu-item__content {
  flex: 1;
  min-width: 0;
}
.rms-menu-item__variant-summary {
  margin: 0.5rem 0 0;
  font-size: 0.875rem;
  color: #64748b;
}
.rms-menu-item__sku-img {
  width: 48px;
  height: 48px;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 6px;
  flex-shrink: 0;
}

.rms-menu-item__card {
  display: block;
  width: 100%;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  min-height: 44px;
}
.rms-menu-item__card:hover {
  opacity: 0.9;
}
.rms-menu-item__card:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
}
</style>
