import { defineConfig } from 'vite'
import { resolve } from 'node:path'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    root: 'demo',
    base: '/arbeitszeiterfassung/',
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
    plugins: [vue(), tailwindcss()],
    build: {
        outDir: '../dist-demo',
        emptyOutDir: true,
    },
})
