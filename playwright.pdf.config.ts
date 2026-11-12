import { chromium, defineConfig } from '@playwright/test';
import { existsSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

// Independent of the authenticated app suite and its APP_URL/storage state.
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
    ?? (!existsSync(chromium.executablePath()) && existsSync('/usr/bin/google-chrome')
        ? '/usr/bin/google-chrome' : undefined);

export default defineConfig({
    testDir: './integration/pdf',
    testMatch: 'migrationIdokladPdf.spec.ts',
    outputDir: process.env.PDF_E2E_OUTPUT_DIR ?? mkdtempSync(join(tmpdir(), 'satflux-pdf-results-')),
    forbidOnly: !!process.env.CI,
    workers: 1,
    retries: 0,
    timeout: 30_000,
    reporter: 'list',
    use: {
        browserName: 'chromium',
        headless: true,
        serviceWorkers: 'block',
        storageState: { cookies: [], origins: [] },
        launchOptions: {
            ...(executablePath ? { executablePath } : {}),
            args: ['--disable-background-networking'],
        },
    },
});
