// @ts-check
const { defineConfig, devices } = require('@playwright/test')

/**
 * E2E tests for RMS frontend. Start the dev server (and optionally the API) before or via webServer.
 * @see frontend/e2e/README.md
 */
module.exports = defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8082',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: 'npx vite --port 8082',
    url: 'http://localhost:8082',
    reuseExistingServer: !process.env.CI,
    timeout: 120000,
  },
})
