/**
 * Shared menu display logic for public template menu sections.
 * Used by Template1Menu and Template2Menu so markup stays template-specific.
 */
import { computed, toRef } from 'vue'
import AvailabilityDisplay from '@/models/AvailabilityDisplay.js'

export function usePublicMenuDisplay(props) {
  const menuGroups = toRef(props, 'menuGroups')
  const menuItems = toRef(props, 'menuItems')
  const currency = toRef(props, 'currency')
  const displayGroups = computed(() => {
    if (menuGroups.value?.length) return menuGroups.value
    if (!menuItems.value?.length) return []
    const heading = props.menuHeading ?? 'Menu'
    return [{ category_name: heading, category_uuid: null, availability: null, items: [...menuItems.value] }]
  })

  /** Availability display model for a menu item or category. */
  function getAvailabilityDisplay(availability) {
    return AvailabilityDisplay.from(availability)
  }

  function formatPrice(price) {
    const n = Number(price)
    const curr = currency.value ?? 'USD'
    if (Number.isNaN(n)) return curr
    const formatted = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
    if (curr === 'USD') return '$' + formatted
    return formatted + ' ' + curr
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

  return {
    displayGroups,
    getAvailabilityDisplay,
    formatPrice,
    itemPrice,
    itemTags,
    comboEntries,
    variantOptionGroupsSummary,
    variantSkus,
    variantSkuLabel,
  }
}
