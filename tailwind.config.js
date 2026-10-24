import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                // brand blue from the logo
                brand: {
                    50: '#eef6fc',
                    100: '#d7e9f7',
                    200: '#b0d3ef',
                    300: '#7fb7e3',
                    400: '#4895d3',
                    500: '#2479bf',
                    600: '#1b63a2',
                    700: '#185084',
                    800: '#17436d',
                    900: '#16385a',
                },
                primary: '#2479bf',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(16 24 40 / 0.04), 0 1px 3px 0 rgb(16 24 40 / 0.06)',
            },
        },
    },

    plugins: [forms],
};
