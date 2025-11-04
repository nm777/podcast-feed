import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import fg from 'fast-glob';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', ...fg.sync('resources/js/pages/**/*.tsx')],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        host: 'localhost',
        port: 5173,
        cors: true,
        hmr: {
            host: 'localhost',
            port: 5173,
        },
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
