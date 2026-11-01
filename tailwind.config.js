const colors = require('tailwindcss/colors');

const n1xDark = {
    50: '#F0FDF4',   // Main Text
    100: '#E1EFEA',  // Bright Text
    200: '#C2D1CC',  // Soft Text
    300: '#94A3A0',  // Secondary Text
    400: '#677E75',  // Muted Text
    500: '#394C43',  // Subtle Surface
    600: '#26352E',  // Borders
    700: '#151E1A',  // Cards
    800: '#101715',  // Sidebar / Containers
    900: '#0B0F0E',  // Background
};

const n1xGreen = {
    50: '#F0FDF4',
    100: '#DCFCE7',
    200: '#BBF7D0',
    300: '#86EFAC',  // Accent
    400: '#4ADE80',
    500: '#22C55E',  // Primary Green
    600: '#16A34A',  // Secondary Green
    700: '#15803D',
    800: '#166534',
    900: '#14532D',
};

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                header: ['"IBM Plex Sans"', '"Roboto"', 'system-ui', 'sans-serif'],
            },
            colors: {
                black: '#0B0F0E',
                primary: n1xGreen,
                green: n1xGreen,
                gray: n1xDark,
                neutral: n1xDark,
                cyan: n1xGreen,
                accent: {
                    DEFAULT: '#86EFAC',
                    light: '#BBF7D0',
                    dark: '#16A34A',
                },
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: theme('colors.neutral.600', '#26352E'),
            }),
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ]
};
