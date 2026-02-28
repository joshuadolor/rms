import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { i18n } from './i18n'
import { requirePaidDirective } from './directives/requirePaid'
import { getStoredAppLocale, getDefaultAppLocale } from './config/app-locales'
import './assets/main.css'

// Main app UI locale: stored selection or VITE_DEFAULT_LOCALE (en, es, ar only).
const appLocale = getStoredAppLocale() ?? getDefaultAppLocale()
i18n.global.locale.value = appLocale
document.documentElement.dir = appLocale === 'ar' ? 'rtl' : 'ltr'

// Single entry for both SPA and Blade-served public page. When Laravel serves the page at
// {slug}.RESTAURANT_DOMAIN, the Blade view outputs #app with data-restaurant-slug and data-locale.
// The same Vue app mounts here; RootView shows PublicRestaurantView when the host is a restaurant
// subdomain (getSubdomainSlug()). Slug comes from the hostname; locale from #app's data-locale when present.
const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(i18n)
requirePaidDirective(app)
app.mount('#app')
