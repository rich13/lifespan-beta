import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

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
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
            protocol: 'ws',
            port: 5173
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
        chunkSizeWarningLimit: 1000
    }
});
