import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Bug reale scoperto in Fase 4 (2026-07-31): le classi Tailwind
        // restituite da metodi PHP come PageStatus::colorClasses()/
        // PageContentType::colorClasses() (app/Enums/*.php) non compaiono
        // mai da nessuna parte nei file sopra (Blade compila la chiamata
        // al metodo, non la stringa che restituisce a runtime) — quindi
        // Tailwind non le vedeva e non generava il CSS corrispondente.
        // Verificato costruendo davvero gli asset e cercando "bg-blue-100"/
        // "bg-purple-100" nel CSS compilato: assenti, da sempre — il colore
        // di sfondo delle pagine "Editoriale"/"Mista" non ha mai reso
        // correttamente in produzione. Scansionare anche app/ risolve alla
        // radice, non solo per queste due classi ma per qualunque classe
        // dinamica futura restituita da PHP fuori da resources/views.
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
