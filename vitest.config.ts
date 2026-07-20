import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        // Heavy component-mount tests occasionally exceed the 5s default under
        // full-suite parallel load (they pass in isolation); 15s absorbs the
        // contention without masking genuinely hung tests.
        testTimeout: 15000,
        setupFiles: ['resources/js/__tests__/setup.ts'],
        include: ['resources/js/__tests__/**/*.{test,spec}.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'lcov'],
            include: ['resources/js/**/*.{ts,vue}'],
            exclude: ['resources/js/__tests__/**'],
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
});
