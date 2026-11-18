import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { updatePageMeta } from '../composables/usePageMeta';

vi.mock('../i18n', () => ({ default: { global: { t: (key: string) => key } } }));

const component = { template: '<div />' };
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { name: 'documentation', path: '/documentation', component },
    { name: 'documentation-show', path: '/documentation/:slug', component },
    { name: 'pricing', path: '/pricing', component },
  ],
});

async function navigate(path: string, overrides?: { title: string }) {
  await router.push(path);
  window.history.replaceState(null, '', path);
  updatePageMeta(router.currentRoute.value, overrides);
}

const markdownLink = () => document.head.querySelector<HTMLLinkElement>('link[data-documentation-markdown]');

describe('documentation head metadata', () => {
  let originalHead: string;
  let originalPath: string;

  beforeEach(() => {
    originalHead = document.head.innerHTML;
    originalPath = window.location.href;
    document.head.innerHTML = '';
  });

  afterEach(() => {
    document.head.innerHTML = originalHead;
    window.history.replaceState(null, '', originalPath);
  });

  it('retains the current server alternate and updates it after a confirmed SPA navigation', async () => {
    document.head.innerHTML = '<link rel="alternate" type="text/markdown" href="/documentation/intro.md" data-documentation-markdown>';
    await navigate('/documentation/intro?utm_source=test#setup');
    expect(document.querySelector('link[rel="canonical"]')?.getAttribute('href')).toBe(`${window.location.origin}/documentation/intro`);
    expect(markdownLink()?.href).toBe(`${window.location.origin}/documentation/intro.md`);

    await navigate('/documentation/billing');
    expect(markdownLink()).toBeNull();
    await navigate('/documentation/billing', { title: 'Billing' });
    expect(markdownLink()?.href).toBe(`${window.location.origin}/documentation/billing.md`);
    expect(markdownLink()?.type).toBe('text/markdown');

    await navigate('/pricing?source=guide');
    expect(markdownLink()).toBeNull();
    expect(document.querySelector('link[rel="canonical"]')?.getAttribute('href')).toBe(`${window.location.origin}/pricing?source=guide`);
  });

  it('keeps the server 404 directive on a missing guide and clears it once a guide loads', async () => {
    document.head.innerHTML = '<meta name="robots" content="noindex" data-documentation-noindex>';
    await navigate('/documentation/missing');
    expect(document.head.querySelector('meta[name="robots"]')).not.toBeNull();
    expect(markdownLink()).toBeNull();

    await navigate('/documentation/intro', { title: 'Introduction' });
    expect(document.head.querySelector('meta[name="robots"]')).toBeNull();
    expect(markdownLink()?.href).toBe(`${window.location.origin}/documentation/intro.md`);
  });
});
