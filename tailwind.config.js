import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // The dashboard is a deliberately light UI. Class strategy (never toggled)
    // stops any leftover `dark:` utility from firing on an OS dark setting.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                },

                // Validated data-viz slots. Charts reference these by role, never
                // by raw hex, so the whole system re-colours from one place.
                series: {
                    1: '#2a78d6', // blue   — primary measure (total scans)
                    2: '#eb6834', // orange — secondary measure (unique visitors)
                    3: '#1baf7a', // aqua   — third category
                },

                state: {
                    good: '#0ca30c',
                    warning: '#fab219',
                    serious: '#ec835a',
                    critical: '#d03b3b',
                },

                ink: {
                    primary: '#0b0b0b',
                    secondary: '#52514e',
                    muted: '#898781',
                },
            },

            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                lift: '0 10px 30px -12px rgb(15 23 42 / 0.25)',
            },
        },
    },

    plugins: [forms],
};
