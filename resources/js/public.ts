import './bootstrap';
import '@fontsource/outfit/400.css';
import '@fontsource/outfit/600.css';
import '@fontsource/outfit/700.css';
import '../css/public.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router/public';
import i18n, { initLocaleFromBackend, preloadActiveLocale } from './i18n';
import AppPublic from './AppPublic.vue';
import { loadMatomoIfConsented } from './services/matomo';

function revealApp(): void {
    document.getElementById('landing-shell')?.remove();
    document.getElementById('app')?.classList.remove('sf-app-pending');
}

function mountPublicSpa(): void {
    const app = createApp(AppPublic);
    app.use(createPinia());
    app.use(router);
    app.use(i18n);
    app.mount('#app');
    revealApp();
    loadMatomoIfConsented();
}

preloadActiveLocale();
mountPublicSpa();
void initLocaleFromBackend();
