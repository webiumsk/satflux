import { defineConfig, devices } from '@playwright/test';
import { AUTH_STATE_PATH } from './e2e/support';

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI
        ? [['html', { open: 'never' }], ['github']]
        : [['html', { open: 'on-failure' }]],

    use: {
        baseURL: process.env.APP_URL ?? 'http://localhost:8080',
        trace: 'on-first-retry',
    },

    projects: [
        // One login per run (auth throttle is 5/min/IP): the setup project
        // signs the seeded user in and saves the storage state; chromium
        // specs start authenticated. Specs that test the login flow itself
        // opt out with an empty storageState (auth.spec, emailLogin.spec).
        { name: 'setup', testMatch: /auth\.setup\.ts/ },
        {
            name: 'chromium',
            dependencies: ['setup'],
            use: {
                ...devices['Desktop Chrome'],
                storageState: AUTH_STATE_PATH,
            },
        },
    ],
});
