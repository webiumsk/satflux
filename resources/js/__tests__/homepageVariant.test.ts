import { afterEach, describe, expect, it, vi } from 'vitest';

afterEach(() => {
  document.body.replaceChildren();
  vi.resetModules();
});

describe('document homepage selection', () => {
  it.each([undefined, '', 'legacy', 'preview', 'SHOWCASE', '" onclick="alert(1)'])(
    'falls back to legacy for %s', async (value) => {
      const element = document.createElement('div');
      if (value !== undefined) element.dataset.homepageVariant = value;
      const { readHomepageVariant } = await import('../services/homepageVariant');
      expect(readHomepageVariant(element)).toBe('legacy');
      expect(readHomepageVariant(null)).toBe('legacy');
    }
  );

  it('captures showcase once for the document rather than reacting to later DOM changes', async () => {
    const app = document.createElement('div');
    app.id = 'app';
    app.dataset.homepageVariant = 'showcase';
    document.body.append(app);

    const selection = await import('../services/homepageVariant');
    expect(selection.homepageVariant).toBe('showcase');
    app.dataset.homepageVariant = 'legacy';
    expect(selection.homepageVariant).toBe('showcase');

    // A fresh document/module load picks up the new server value.
    vi.resetModules();
    expect((await import('../services/homepageVariant')).homepageVariant).toBe('legacy');
  });
});
