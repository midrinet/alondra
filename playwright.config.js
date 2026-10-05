// @ts-check
const { defineConfig, devices } = require('@playwright/test');

// slowMo only when the run is visible (headed / ui / debug).
const isVisible = process.argv.includes('--headed')
  || process.argv.includes('--ui')
  || process.argv.includes('--debug')
  || !!process.env.PWDEBUG;

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './tests-e2e/specs',
  outputDir: process.env.CI ? '/tmp/test-results' : './tests-e2e/test-results/',
  timeout: process.env.CI ? 2 * 60 * 1000 : 5 * 60 * 1000, // 2 min on CI, 5 locally
  /* Run tests in files in parallel */
  fullyParallel: false,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* No retries: a retry doubles the time/quota a hanging test burns. */
  retries: 0,
  /* Opt out of parallel tests on CI. */
  workers: 1,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: process.env.CI ? 'dot' : 'list',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: process.env.WP_URL,
    /* Slow motion only on visible runs (3500ms per action); 0 in headless/CI. */
    launchOptions: { slowMo: isVisible ? 3500 : 0 },
    /* Keep a trace for any failed test (no retries now, so capture on failure). */
    trace: 'retain-on-failure',
    // Take a screenshot when a test fails.
    screenshot: 'only-on-failure',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chromium'] },
    },
  ],
});

