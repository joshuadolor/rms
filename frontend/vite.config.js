import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// When set, Vite proxies /api and /sanctum to this URL (e.g. http://localhost:3000 or http://api:3000 in Docker).
// Leave unset to disable proxy; then set VITE_API_URL to the full API base URL.
const proxyTarget = process.env.VITE_PROXY_TARGET || ''

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 8080,
    host: true, // listen on 0.0.0.0 for Docker
    allowedHosts: ['localhost', '127.0.0.1', 'rms.local', '.rms.local'],
    proxy: proxyTarget
      ? {
          '/api': {
            target: proxyTarget,
            changeOrigin: true,
          },
          '/sanctum': {
            target: proxyTarget,
            changeOrigin: true,
          },
          // Do NOT proxy /r — the Vue app serves /r/:slug and renders Template1/Template2 (from resources/generic-templates).
          // Proxying /r would return Laravel Blade HTML and the new templates would never show.
        }
      : undefined,
  },
})
