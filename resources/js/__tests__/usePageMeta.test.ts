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

async function navigate(path: string, overrides?: { title: string; description?: string }) {
  await router.push(path);
  window.history.replaceState(null, '', path);
  updatePageMeta(router.currentRoute.value, overrides);
}

const markdownLink = () => document.head.querySelector<HTMLLinkElement>('link[data-documentation-markdown]');
const canonical = () => document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]')?.href;
const metaContent = (selector: string) => document.head.querySelector<HTMLMetaElement>(`meta[${selector}]`)?.content;

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

  it('preserves server metadata on entry and updates both social cards for the next guide', async () => {
    const site = 'https://panel.test:8443';
    document.head.innerHTML = `
      <meta name="satflux-app-url" content="${site}/">
      <title>Server guide - satflux.io</title>
      <meta name="description" content="Server guide description.">
      <meta property="og:title" content="Server guide - satflux.io">
      <meta property="og:description" content="Server guide description.">
      <meta property="og:url" content="${site}/documentation/intro">
      <meta name="twitter:title" content="Server guide - satflux.io">
      <meta name="twitter:description" content="Server guide description.">
      <meta name="twitter:url" content="${site}/documentation/intro">
      <link rel="canonical" href="${site}/documentation/intro">
      <link rel="alternate" type="text/markdown" href="${site}/documentation/intro.md" data-documentation-markdown>
    `;

    await navigate('/documentation/intro?utm_source=test#example');
    expect(document.title).toBe('Server guide - satflux.io');
    expect(metaContent('property="og:title"')).toBe('Server guide - satflux.io');
    expect(metaContent('name="twitter:description"')).toBe('Server guide description.');
    expect(canonical()).toBe(`${site}/documentation/intro`);

    await router.push('/documentation/billing/?source=intro#payments');
    updatePageMeta(router.currentRoute.value, { title: 'Billing', description: 'Invoices and payments.' });
    expect(document.title).toBe('Billing - satflux.io');
    expect(metaContent('name="description"')).toBe('Invoices and payments.');
    for (const [attribute, prefix] of [['property', 'og'], ['name', 'twitter']]) {
      expect(metaContent(`${attribute}="${prefix}:title"`)).toBe('Billing - satflux.io');
      expect(metaContent(`${attribute}="${prefix}:description"`)).toBe('Invoices and payments.');
      expect(metaContent(`${attribute}="${prefix}:url"`)).toBe(`${site}/documentation/billing`);
    }
    expect(canonical()).toBe(`${site}/documentation/billing`);
    expect(markdownLink()?.href).toBe(`${site}/documentation/billing.md`);

    await navigate('/documentation?search=payments#results');
    expect(canonical()).toBe(`${site}/documentation`);
    await navigate('/pricing?source=guide');
    expect(canonical()).toBe(`${window.location.origin}/pricing?source=guide`);
  });
});
