import { config } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

// Minimal i18n for components that use useI18n()
const i18n = createI18n({ legacy: false, locale: 'en', messages: { en: {} } });

config.global.plugins = [i18n];
