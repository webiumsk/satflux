import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        minify: process.env.VITE_DISABLE_MINIFY === 'true' ? false : 'esbuild',
        sourcemap: false,
        target: 'es2020',
        cssCodeSplit: true,
        rollupOptions: {
            output: {
                chunkFileNames: 'assets/[name]-[hash].js',
                entryFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]',
                format: 'es',
                manualChunks(id) {
                    if (id.includes('node_modules/vue/') || id.includes('node_modules/@vue/')) {
                        return 'vue';
                    }
                    if (id.includes('node_modules/vue-router')) {
                        return 'vue-router';
                    }
                    if (id.includes('node_modules/vue-i18n') || id.includes('node_modules/@intlify')) {
                        return 'vue-i18n';
                    }
                    if (id.includes('node_modules/pinia')) {
                        return 'pinia';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/public.css',
                'resources/js/app.ts',
                'resources/js/public.ts',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
