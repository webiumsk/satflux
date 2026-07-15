import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import api from './services/api';

export const supportedLocales = ['en', 'sk', 'es', 'cs', 'de'] as const;
export type SupportedLocale = typeof supportedLocales[number];

const defaultLocale: SupportedLocale = 'en';

function getLocale(): SupportedLocale {
    const stored = localStorage.getItem('locale');
    if (stored && supportedLocales.includes(stored as SupportedLocale)) {
        return stored as SupportedLocale;
    }
    return defaultLocale;
}

async function loadLocaleMessages(locale: SupportedLocale): Promise<Record<string, unknown>> {
    switch (locale) {
        case 'en':
            return en;
        case 'sk':
            return (await import('./locales/sk.json')).default;
        case 'es':
            return (await import('./locales/es.json')).default;
        case 'cs':
            return (await import('./locales/cs.json')).default;
        case 'de':
            return (await import('./locales/de.json')).default;
        default:
            return en;
    }
}

export async function initLocaleFromBackend(): Promise<void> {
    try {
        const localStorageLocale = getLocale();

        if (localStorageLocale && localStorageLocale !== defaultLocale) {
            try {
                await api.post('/locale', { locale: localStorageLocale });
            } catch (error) {
                console.warn('Failed to sync locale to backend:', error);
            }
        }

        const response = await api.get('/locale');
        const backendLocale = response.data.current;

        if (backendLocale && supportedLocales.includes(backendLocale as SupportedLocale)) {
            const locale = backendLocale as SupportedLocale;

            if (!localStorage.getItem('locale')) {
                await setLocale(locale);
                localStorage.setItem('locale', locale);
            } else if (!i18n.global.availableLocales.includes(localStorageLocale)) {
                await setLocale(localStorageLocale);
            }
        }
    } catch (error) {
        console.warn('Failed to fetch locale from backend, using localStorage:', error);
        const locale = getLocale();
        if (!i18n.global.availableLocales.includes(locale)) {
            await setLocale(locale);
        }
    }
}

const i18n = createI18n({
    legacy: false,
    locale: getLocale(),
    fallbackLocale: defaultLocale,
    messages: {
        en,
    },
    missingWarn: false,
    fallbackWarn: false,
});

export async function setLocale(locale: SupportedLocale) {
    if (!supportedLocales.includes(locale)) {
        console.warn(`Locale ${locale} is not supported`);
        return;
    }

    if (i18n.global.availableLocales.includes(locale)) {
        i18n.global.locale.value = locale;
        localStorage.setItem('locale', locale);
        document.documentElement.lang = locale;
        return;
    }

    try {
        const messages = await loadLocaleMessages(locale);
        i18n.global.setLocaleMessage(locale, messages);
        i18n.global.locale.value = locale;
        localStorage.setItem('locale', locale);
        document.documentElement.lang = locale;
    } catch (error) {
        console.error(`Failed to load locale ${locale}:`, error);
        i18n.global.locale.value = defaultLocale;
    }
}

/** Load the active locale file without blocking first paint (en is bundled). */
export function preloadActiveLocale(): void {
    const locale = getLocale();
    if (locale !== defaultLocale) {
        void setLocale(locale);
    } else {
        document.documentElement.lang = locale;
    }
}

export default i18n;
