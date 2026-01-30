import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import router from './router';
import i18n, { initLocaleFromBackend } from './i18n';
import App from './App.vue';

const el = document.getElementById('app');
const isInertia = el?.hasAttribute('data-page');

function mountSpa(): void {
    const app = createApp(App);
    app.use(createPinia());
    app.use(router);
    app.use(i18n);
    app.mount('#app');
}

function mountInertia(): void {
    createInertiaApp({
        title: (title) => (title ? `${title} - ${import.meta.env.VITE_APP_NAME || 'satflux.io'}` : 'satflux.io'),
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.vue`,
                import.meta.glob('./pages/**/*.vue'),
            ),
        setup({ el: mountEl, App: InertiaApp, props, plugin }) {
            const app = createApp({ render: () => h(InertiaApp, props) });
            app.use(plugin);
            app.use(createPinia());
            app.use(i18n);
            app.mount(mountEl);
        },
    });
}

// Initialize locale from backend before mounting app
initLocaleFromBackend().then(() => {
    if (isInertia) {
        mountInertia();
    } else {
        mountSpa();
    }
});








