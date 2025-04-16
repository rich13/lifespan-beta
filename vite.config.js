import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/js/app.js',
                'resources/js/routes.js'
            ],
            refresh: true,
        }),
    ],
    // Force HTTPS in production and set proper base path
    base: process.env.APP_ENV === 'production' ? 'https://lifespan-beta-production.up.railway.app/' : '/',
    server: {
        https: false, // Use HTTP for development server
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true
        },
        strictPort: true,
        fs: {
            strict: false
        }
    },
    build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks: undefined,
            },
        },
        // Properly handle asset URLs
        assetsInlineLimit: 0,
    },
    resolve: {
        alias: {
            '~bootstrap': resolve(__dirname, 'node_modules/bootstrap'),
            '~bootstrap-icons': resolve(__dirname, 'node_modules/bootstrap-icons'),
        }
    },
});
