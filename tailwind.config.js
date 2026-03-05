import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const TYPE_SCALE_FACTOR = 4 / 3;

function scaleCssLength(value) {
    if (typeof value !== 'string') {
        return value;
    }

    const match = value.trim().match(/^(-?\d*\.?\d+)(rem|px)$/);
    if (!match) {
        return value;
    }

    const scaled = Number(match[1]) * TYPE_SCALE_FACTOR;
    const rounded = Number(scaled.toFixed(4)).toString();

    return `${rounded}${match[2]}`;
}

function scaleFontSizes(fontSizes) {
    return Object.fromEntries(
        Object.entries(fontSizes).map(([key, definition]) => {
            if (typeof definition === 'string') {
                return [key, scaleCssLength(definition)];
            }

            if (!Array.isArray(definition)) {
                return [key, definition];
            }

            const [fontSize, options] = definition;
            const scaledSize = scaleCssLength(fontSize);

            if (!options || typeof options !== 'object') {
                return [key, [scaledSize, options]];
            }

            const scaledOptions = { ...options };

            if (typeof scaledOptions.lineHeight === 'string') {
                scaledOptions.lineHeight = scaleCssLength(scaledOptions.lineHeight);
            }

            return [key, [scaledSize, scaledOptions]];
        })
    );
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
        fontSize: scaleFontSizes(defaultTheme.fontSize),
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
