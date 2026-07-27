import { defineConfig, devices } from '@playwright/test';

/**
 * Browser smoke tests.
 *
 * These exist because of a specific failure: charts silently rendered as blank
 * boxes for months, and nothing in a PHP test suite could see it. A chart is
 * only "working" if pixels land on the canvas, which needs a real browser.
 *
 * Keep this suite small and about things only a browser can answer — rendering,
 * JS errors, and behaviour after an Alpine AJAX swap. Everything else belongs in
 * the much faster PHP suite.
 */
export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.APP_URL || 'http://127.0.0.1:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                // Environments that ship a pre-installed Chromium (containers,
                // sandboxes) can point at it instead of downloading a second
                // copy. CI leaves this unset and uses Playwright's own build.
                launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH
                    ? {
                        executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH,
                        args: ['--no-sandbox'],
                    }
                    : {},
            },
        },
    ],
});
