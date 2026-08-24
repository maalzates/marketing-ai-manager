import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': new URL('./resources/js', import.meta.url).pathname,
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        // nginx proxies this dev server, so the browser only ever talks to one origin. The
        // client must therefore be told to connect back through nginx on :80 and on the
        // path nginx proxies — not to :5173, which is no longer published.
        hmr: {
            host: 'localhost',
            clientPort: 80,
            protocol: 'ws',
            path: '/vite-hmr',
        },
        // Vite refuses requests whose Host header it does not recognise, and the proxied
        // ones arrive as `localhost`.
        allowedHosts: ['localhost'],
        origin: 'http://localhost',
        watch: {
            usePolling: true,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
