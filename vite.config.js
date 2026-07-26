import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        // Tailwind v4's first-party Vite plugin. Faster than routing through
        // PostCSS, and it means there is no postcss.config.js to keep in sync.
        tailwindcss(),
    ],
});
