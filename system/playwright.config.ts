import { defineConfig, devices } from '@playwright/test';

const externalBaseUrl =
    process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:18000';

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: 'line',
    use: {
        baseURL: externalBaseUrl,
        trace: 'on-first-retry',
    },
    // Prefer the Compose app on :18000. Only spawn a local artisan server when
    // an explicit override asks for a host PHP runtime.
    webServer: process.env.PLAYWRIGHT_START_SERVER
        ? {
              command: 'php artisan serve --host=127.0.0.1 --port=8000',
              cwd: process.cwd(),
              reuseExistingServer: !process.env.CI,
              url: 'http://127.0.0.1:8000/up',
          }
        : undefined,
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
