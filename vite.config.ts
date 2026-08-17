import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
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
    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, 'resources/js'),
        },
    },
    build: {
        // Keep the catalog pages lean: every Inertia page gets its own chunk, and the
        // runtime everybody shares is hoisted into one long-lived `vendor` chunk.
        // Rolldown (Vite 8) only accepts the function form of manualChunks.
        rollupOptions: {
            output: {
                manualChunks(id: string) {
                    if (/node_modules\/(vue|@vue|@inertiajs)\//.test(id)) {
                        return 'vendor';
                    }

                    return undefined;
                },
            },
        },
    },
});
