import { describe, it, expect } from 'vitest';
import { createI18n } from 'vue-i18n';
import en from '../locales/en.json';
import sk from '../locales/sk.json';
import es from '../locales/es.json';

const locales = { en, sk, es } as const;

function flattenMessages(obj: Record<string, unknown>, prefix = ''): string[] {
  const keys: string[] = [];
  for (const [key, value] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      keys.push(...flattenMessages(value as Record<string, unknown>, path));
    } else if (typeof value === 'string') {
      keys.push(path);
    }
  }

  return keys;
}

describe('locale messages', () => {
  for (const [locale, messages] of Object.entries(locales)) {
    it(`parses all ${locale} strings without vue-i18n linked-format errors`, () => {
      const i18n = createI18n({
        legacy: false,
        locale,
        messages: { [locale]: messages },
      });

      const t = i18n.global.t;
      for (const key of flattenMessages(messages as Record<string, unknown>)) {
        expect(() => t(key), key).not.toThrow();
      }
    });
  }
});
