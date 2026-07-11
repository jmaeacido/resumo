import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                resumo: {
                    50: '#eef7f5',
                    100: '#d5ebe7',
                    200: '#aed8d1',
                    300: '#7fbeb3',
                    400: '#529d91',
                    500: '#388277',
                    600: '#2b6860',
                    700: '#255450',
                    800: '#214543',
                    900: '#1e3a39',
                    950: '#0d3d3b',
                },
            },
        },
    },

    plugins: [forms],
};
