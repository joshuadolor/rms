// @ts-check
const { defineConfig, devices } = require('@playwright/test')

/**
 * E2E tests for RMS frontend. Start the dev server (and optionally the API) before or via webServer.
 * @see frontend/e2e/README.md
 */
const isCI = process.env.CI === 'true' || process.env.CI === '1'
const defaultPort = process.env.PLAYWRIGHT_PORT || '8083'

module.exports = defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: isCI,
  retries: isCI ? 2 : 0,
  workers: isCI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || `http://localhost:${defaultPort}`,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: `npx vite --port ${defaultPort}`,
    url: process.env.PLAYWRIGHT_BASE_URL || `http://localhost:${defaultPort}`,
    // Allow reusing an already-running Vite server (common in local dev + IDE runners).
    // When nothing is running, Playwright will still start the server via `command`.
    reuseExistingServer: true,
    timeout: 120000,
    // Proxy /api to the Laravel API so tests can use relative URLs. E2E=1 so Vite does not proxy /r (SPA serves /r/:slug).
    env: {
      ...process.env,
      E2E: '1',
      VITE_PROXY_TARGET: process.env.VITE_PROXY_TARGET || process.env.E2E_API_BASE || 'http://localhost:3000',
    },
  },
})
