import { createI18n } from 'vue-i18n';
import en from './locales/en.json';

// Supported locales
export const supportedLocales = ['en', 'cz', 'de', 'es', 'fr', 'hu', 'pl', 'sk'] as const;
export type SupportedLocale = typeof supportedLocales[number];

// Default locale
const defaultLocale: SupportedLocale = 'en';

// Get locale from localStorage or use default
function getLocale(): SupportedLocale {
    const stored = localStorage.getItem('locale');
    if (stored && supportedLocales.includes(stored as SupportedLocale)) {
        return stored as SupportedLocale;
    }
    return defaultLocale;
}

// Create i18n instance
const i18n = createI18n({
    legacy: false, // Use Composition API mode
    locale: getLocale(),
    fallbackLocale: defaultLocale,
    messages: {
        en,
        // Other locales will be loaded dynamically
    },
    missingWarn: false, // Disable warnings for missing translations in development
    fallbackWarn: false,
});

// Function to set locale
export async function setLocale(locale: SupportedLocale) {
    if (!supportedLocales.includes(locale)) {
        console.warn(`Locale ${locale} is not supported`);
        return;
    }

    // If locale is already loaded, just switch
    if (i18n.global.availableLocales.includes(locale)) {
        i18n.global.locale.value = locale;
        localStorage.setItem('locale', locale);
        return;
    }

    // Otherwise, load locale dynamically
    try {
        const messages = await import(`./locales/${locale}.json`);
        i18n.global.setLocaleMessage(locale, messages.default);
        i18n.global.locale.value = locale;
        localStorage.setItem('locale', locale);
    } catch (error) {
        console.error(`Failed to load locale ${locale}:`, error);
        // Fallback to default locale
        i18n.global.locale.value = defaultLocale;
    }
}

export default i18n;

