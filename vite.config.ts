import { defineConfig, type Plugin } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

const viteDevOrigin = process.env.VITE_DEV_SERVER_ORIGIN || 'http://localhost:8080';

/** Evolu sqlite-wasm workers break when prebundled into node_modules/.vite/deps. */
const evoluOptimizeExclude = [
    '@sqlite.org/sqlite-wasm',
    '@evolu/sqlite-wasm',
    '@evolu/web',
    '@evolu/common',
    '@evolu/vue',
];

function wasmMimeType(): Plugin {
    return {
        name: 'wasm-mime-type',
        configureServer(server) {
            server.middlewares.use((req, res, next) => {
                if (req.url?.split('?')[0]?.endsWith('.wasm')) {
                    res.setHeader('Content-Type', 'application/wasm');
                }
                next();
            });
        },
    };
}

export default defineConfig({
    // vue-i18n JIT compilation: compiles messages to AST without new Function/eval,
    // so the CSP script-src does not need 'unsafe-eval' (vue-i18n >= 9.3).
    define: {
        __INTLIFY_JIT_COMPILATION__: true,
    },
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
                    // Bundle guide + smart paste into the WalletConnection route chunk (not a tiny
                    // shared file that breaks when browsers cache an older route chunk hash).
                    if (
                        id.includes('WalletConnectionTypeGuide')
                        || id.includes('WalletConnectionSmartPaste')
                    ) {
                        return 'WalletConnection';
                    }
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
    assetsInclude: ['**/*.wasm'],
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/public.css',
                'resources/js/app.ts',
                'resources/js/public.ts',
            ],
            // Avoid full-page reload when lang/routes change during backend work (Docker scheduler, etc.).
            refresh: ['resources/views/**'],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wasmMimeType(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    optimizeDeps: {
        exclude: evoluOptimizeExclude,
    },
    worker: {
        format: 'es',
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Laravel/nginx serves the page on :8080 and proxies /@vite, /node_modules, etc.
        // origin + clientPort keep workers/WASM on the same origin (no [::1]:5173 CORS block).
        origin: viteDevOrigin,
        cors: true,
        hmr: {
            host: 'localhost',
            port: 5173,
            clientPort: 8080,
            protocol: 'ws',
            // Proxied in docker/nginx/default.conf - root "/" would hit Laravel instead of Vite.
            path: '/@vite-hmr',
        },
    },
});
