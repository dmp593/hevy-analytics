import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // System stack rather than a webfont from a third-party CDN.
                // A remote font is the same failure mode as the Chart.js CDN
                // was: when the request does not land, the page silently
                // degrades. It is also a third-party request on every page
                // load, which is a disclosure we would rather not owe EU users.
                sans: defaultTheme.fontFamily.sans,
            },
        },
    },

    plugins: [forms, typography],
};
