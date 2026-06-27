import { config } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

// Evolu Sync.js expects Set.prototype.difference (ES2025); Node 20 CI may lack it.
if (typeof Set !== 'undefined' && !Set.prototype.difference) {
    Set.prototype.difference = function difference<T>(this: Set<T>, other: ReadonlySet<T>): Set<T> {
        const out = new Set(this);
        for (const value of other) {
            out.delete(value);
        }
        return out;
    };
}

// Minimal i18n for components that use useI18n()
const i18n = createI18n({ legacy: false, locale: 'en', messages: { en: {} } });

config.global.plugins = [i18n];
