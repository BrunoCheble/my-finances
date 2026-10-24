import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

// brand blue from the logo
const brand = {
    50: '#eef6fc', 100: '#d7e9f7', 200: '#b0d3ef', 300: '#7fb7e3', 400: '#4895d3',
    500: '#2479bf', 600: '#1b63a2', 700: '#185084', 800: '#17436d', 900: '#16385a', 950: '#0e2440',
};

/*
 * Theming: every color the views use is a CSS variable, so the dark theme
 * (class "dark" on <html>) is one set of variables instead of dark: variants
 * on every element. Neutral scale and surfaces are hand-picked; the tinted
 * families are mirrored (50 <-> 950 ...) and blended into the dark surface.
 */
const families = {
    slate: colors.slate, gray: colors.slate, brand, rose: colors.rose, red: colors.red,
    emerald: colors.emerald, green: colors.green, amber: colors.amber, yellow: colors.yellow,
    purple: colors.purple, indigo: brand, blue: brand, orange: colors.orange,
};
const steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

const darkSurface = '#141c2b';
const darkNeutral = {
    white: darkSurface,
    50: '#0c121e', 100: '#1d2738', 200: '#2a3547', 300: '#3b475b', 400: '#66748a', 500: '#94a3b8',
    600: '#a8b5c7', 700: '#c4cedb', 800: '#dde3eb', 900: '#eef2f7', 950: '#f8fafc',
};

const rgb = (hex) => {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
};
const mix = (a, b, weight) => rgb(a).map((v, i) => Math.round(v * weight + rgb(b)[i] * (1 - weight)));
const triplet = (value) => (Array.isArray(value) ? value : rgb(value)).join(' ');

// dark step for a tinted family: light tints become dark tints, dark text becomes light text
const darkTint = (scale, step) => ({
    50: mix(scale[900], darkSurface, 0.28),
    100: mix(scale[800], darkSurface, 0.45),
    200: mix(scale[700], darkSurface, 0.6),
    300: mix(scale[600], darkSurface, 0.8),
    400: scale[500],
    500: scale[400],
    600: scale[400],
    700: scale[300],
    800: scale[200],
    900: scale[100],
    950: scale[50],
}[step]);

const lightVars = { '--c-white': triplet('#ffffff') };
const darkVars = { '--c-white': triplet(darkNeutral.white) };
const palette = { white: 'rgb(var(--c-white) / <alpha-value>)', black: '#000', transparent: 'transparent', current: 'currentColor' };

for (const [name, scale] of Object.entries(families)) {
    palette[name] = {};
    for (const step of steps) {
        const variable = `--c-${name}-${step}`;
        lightVars[variable] = triplet(scale[step]);
        darkVars[variable] = triplet(name === 'slate' || name === 'gray' ? darkNeutral[step] : darkTint(scale, step));
        palette[name][step] = `rgb(var(${variable}) / <alpha-value>)`;
    }
}

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        colors: palette,
        extend: {
            colors: {
                primary: 'rgb(var(--c-brand-500) / <alpha-value>)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(16 24 40 / 0.04), 0 1px 3px 0 rgb(16 24 40 / 0.06)',
            },
        },
    },

    plugins: [
        forms,
        plugin(({ addBase }) => addBase({
            // .theme-light keeps a subtree light in dark mode (e.g. the brand panel of the login)
            ':root, .theme-light': lightVars,
            '.dark': darkVars,
            '.dark .theme-light': lightVars,
        })),
    ],
};
