import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        // Keep production minification enabled by default; allow debug opt-out via env.
        minify: process.env.VITE_DISABLE_MINIFY === 'true' ? false : 'esbuild',
        sourcemap: false,
        target: 'es2020',
        cssCodeSplit: true,
        rollupOptions: {
            output: {
                // Use simpler chunk names for better compatibility
                chunkFileNames: 'assets/[name]-[hash].js',
                entryFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]',
                // Ensure proper encoding
                format: 'es',
            },
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
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








