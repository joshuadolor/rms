/**
 * Breadcrumb config: route name -> { label, parent }.
 * First crumb matches the left nav section (Dashboard, Restaurants, or Profile & Settings).
 * Label can be a string or a function (route, breadcrumbStore) => string for dynamic labels.
 */
export const BREADCRUMB_CONFIG = {
  App: { label: 'Dashboard', parent: null },
  Restaurants: { label: 'Restaurants', parent: null },
  RestaurantNew: { label: 'Add restaurant', parent: 'Restaurants' },
  RestaurantDetail: {
    label: (_, store) => store.restaurantName || 'Restaurant',
    parent: 'Restaurants',
  },
  RestaurantEdit: { label: 'Edit', parent: 'RestaurantDetail' },
  RestaurantContent: { label: 'Languages & description', parent: 'RestaurantDetail' },
  RestaurantMenuItems: { label: 'Menu', parent: 'RestaurantDetail' },
  RestaurantMenuItemNew: { label: 'Add item', parent: 'RestaurantMenuItems' },
  RestaurantMenuItemEdit: {
    label: (_, store) => store.menuItemName ? `Edit ${store.menuItemName}` : 'Edit item',
    parent: 'RestaurantMenuItems',
  },
  Profile: { label: 'Profile & Settings', parent: null },
}

export function getBreadcrumbTrail(routeName, breadcrumbStore) {
  const trail = []
  let name = routeName
  while (name && BREADCRUMB_CONFIG[name]) {
    const config = BREADCRUMB_CONFIG[name]
    const label = typeof config.label === 'function' ? config.label(null, breadcrumbStore) : config.label
    trail.unshift({ name, label })
    name = config.parent
  }
  return trail
}
