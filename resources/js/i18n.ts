import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import api from './services/api';

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

// Function to initialize locale from backend (syncs backend session with localStorage)
export async function initLocaleFromBackend(): Promise<void> {
    try {
        const localStorageLocale = getLocale();
        
        // First, ensure backend session matches localStorage (if localStorage has a value)
        if (localStorageLocale && localStorageLocale !== defaultLocale) {
            // Sync backend session with localStorage
            try {
                await api.post('/locale', { locale: localStorageLocale });
            } catch (error) {
                // If sync fails, continue with localStorage value
                console.warn('Failed to sync locale to backend:', error);
            }
        }
        
        // Then check backend and use it only if localStorage is not set
        const response = await api.get('/locale');
        const backendLocale = response.data.current;
        
        if (backendLocale && supportedLocales.includes(backendLocale as SupportedLocale)) {
            const locale = backendLocale as SupportedLocale;
            
            // Only use backend locale if localStorage doesn't have a preference
            if (!localStorage.getItem('locale')) {
                // No localStorage preference, use backend
                await setLocale(locale);
                localStorage.setItem('locale', locale);
            } else {
                // localStorage has preference, ensure locale is loaded
                if (!i18n.global.availableLocales.includes(localStorageLocale)) {
                    await setLocale(localStorageLocale);
                }
            }
        }
    } catch (error) {
        console.warn('Failed to fetch locale from backend, using localStorage:', error);
        // Fallback to localStorage if backend request fails
        const locale = getLocale();
        if (!i18n.global.availableLocales.includes(locale)) {
            await setLocale(locale);
        }
    }
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
        // Use explicit imports for better Vite compatibility in production
        // Only import locales that actually exist
        let messages;
        switch (locale) {
            case 'en':
                messages = await import('./locales/en.json');
                break;
            case 'sk':
                messages = await import('./locales/sk.json');
                break;
            case 'es':
                messages = await import('./locales/es.json');
                break;
            // For locales that don't exist yet, fallback to English
            case 'cz':
            case 'de':
            case 'fr':
            case 'hu':
            case 'pl':
            default:
                console.warn(`Locale ${locale} not yet implemented, falling back to English`);
                messages = await import('./locales/en.json');
                // Still set the locale value, but use English messages
                i18n.global.setLocaleMessage(locale, messages.default);
                i18n.global.locale.value = locale;
                localStorage.setItem('locale', locale);
                return;
        }
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

