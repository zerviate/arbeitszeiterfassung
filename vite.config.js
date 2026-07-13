import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
        tailwindcss(),
    ],
    server: {
        host: true,
        port: 5174,
        hmr: {
            host: 'arbeitszeiterfassung.ddev.site',
            port: 5174,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
