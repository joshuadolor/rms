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
  MenuItems: { label: 'Menu items', parent: null },
  MenuItemNew: { label: 'Add menu item', parent: 'MenuItems' },
  MenuItemEdit: {
    label: (_, store) => store.menuItemName ? `Edit ${store.menuItemName}` : 'Edit item',
    parent: 'MenuItems',
  },
  MenuItemTags: { label: 'Menu item tags', parent: 'MenuItems' },
  Feedbacks: { label: 'Feedbacks', parent: null },
  FeedbacksList: {
    label: (_, store) => store.restaurantName || 'Reviews',
    parent: 'Feedbacks',
  },
  __main_menu__: { label: 'Main menu', parent: 'RestaurantDetail' },
  RestaurantMenuItems: {
    label: (_, store) => store.menuName || 'Menu',
    parent: '__main_menu__',
  },
  __category_label__: { label: 'Category', parent: 'RestaurantMenuItems' },
  __category__: {
    label: (_, store) => store.categoryName || 'Category',
    parent: '__category_label__',
  },
  CategoryMenuItems: { label: 'Menu items', parent: '__category__' },
  RestaurantMenuItemNew: { label: 'Add item', parent: 'RestaurantMenuItems' },
  RestaurantMenuItemEdit: {
    label: (_, store) => store.menuItemName ? `Edit ${store.menuItemName}` : 'Edit item',
    parent: 'RestaurantMenuItems',
  },
  Profile: { label: 'Profile & Settings', parent: null },
}

/** Resolve parent for add/edit item routes: CategoryMenuItems when return=category-items and category_uuid present. */
function getParent(routeName, route) {
  const config = BREADCRUMB_CONFIG[routeName]
  if (!config) return null
  if (routeName === 'RestaurantMenuItemNew' || routeName === 'RestaurantMenuItemEdit') {
    if (route?.query?.return === 'category-items' && route?.query?.category_uuid) return 'CategoryMenuItems'
  }
  return config.parent
}

export function getBreadcrumbTrail(routeName, breadcrumbStore, route = null) {
  const trail = []
  let name = routeName
  while (name && BREADCRUMB_CONFIG[name]) {
    const config = BREADCRUMB_CONFIG[name]
    const label = typeof config.label === 'function' ? config.label(null, breadcrumbStore) : config.label
    trail.unshift({ name, label })
    name = getParent(name, route) ?? config.parent
  }
  return trail
}
