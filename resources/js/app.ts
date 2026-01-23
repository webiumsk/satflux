import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import i18n, { initLocaleFromBackend } from './i18n';
import App from './App.vue';

// Initialize locale from backend before mounting app
initLocaleFromBackend().then(() => {
    const app = createApp(App);

    app.use(createPinia());
    app.use(router);
    app.use(i18n);

    app.mount('#app');
});








