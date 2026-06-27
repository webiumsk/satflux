import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

const viteDevOrigin = process.env.VITE_DEV_SERVER_ORIGIN || 'http://localhost:8080';

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
    optimizeDeps: {
        // Evolu: sqlite-wasm must not be prebundled; @evolu/web must stay unbundled so
        // Db.worker.js resolves to node_modules/@evolu/web/... (not missing .vite/deps/Db.worker.js).
        exclude: [
            '@sqlite.org/sqlite-wasm',
            '@evolu/sqlite-wasm',
            '@evolu/web',
            '@evolu/common',
            '@evolu/vue',
        ],
    },
    worker: {
        format: 'es',
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Page is served via Laravel/nginx :8080; asset URLs use origin below.
        // HMR WebSocket connects directly to Vite :5173 (nginx does not proxy it reliably).
        origin: viteDevOrigin,
        cors: true,
        hmr: {
            host: 'localhost',
            port: 5173,
            protocol: 'ws',
        },
    },
});
