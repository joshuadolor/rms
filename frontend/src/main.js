import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { i18n } from './i18n'
import './assets/main.css'

// Single entry for both SPA and Blade-served public page. When Laravel serves the page at
// {slug}.RESTAURANT_DOMAIN, the Blade view outputs #app with data-restaurant-slug and data-locale.
// The same Vue app mounts here; RootView shows PublicRestaurantView when the host is a restaurant
// subdomain (getSubdomainSlug()). Slug comes from the hostname; locale from #app's data-locale when present.
const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(i18n)
app.mount('#app')
