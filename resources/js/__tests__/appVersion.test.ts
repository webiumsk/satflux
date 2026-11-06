import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';

const mocks = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('../services/api', () => ({ default: { get: mocks.get } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

let wrappers: VueWrapper[] = [];

/** Mount the actual shared footer so public metadata caching is verified at its consumer boundary. */
async function mountFooter(className?: string): Promise<VueWrapper> {
  const { default: FooterContent } = await import('../components/layout/AppFooterContent.vue');
  const wrapper = mount(FooterContent, {
    attrs: { class: className },
    global: { stubs: { RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' } } },
  });
  wrappers.push(wrapper);
  return wrapper;
}

beforeEach(() => {
  vi.resetModules();
  mocks.get.mockReset();
});

afterEach(() => {
  wrappers.forEach(wrapper => wrapper.unmount());
  wrappers = [];
});

describe('shared public footer version', () => {
  it('shares the in-flight request across concurrent mounts, including a mobile-hidden footer', async () => {
    let resolve!: (value: { data: { version: string } }) => void;
    mocks.get.mockReturnValue(new Promise<{ data: { version: string } }>(done => { resolve = done; }));
    const first = await mountFooter();
    const hidden = await mountFooter('hidden md:block');
    await flushPromises();
    expect(mocks.get).toHaveBeenCalledExactlyOnceWith('/version');
    resolve({ data: { version: '2.0.0-test' } });
    await flushPromises();
    expect(first.text()).toContain('v2.0.0-test');
    expect(hidden.text()).toContain('v2.0.0-test');
  });

  it('reuses a successful version when footer navigation remounts it', async () => {
    mocks.get.mockResolvedValue({ data: { version: '2.0.0-test' } });
    const first = await mountFooter();
    await flushPromises();
    first.unmount();
    const next = await mountFooter();
    await flushPromises();
    expect(mocks.get).toHaveBeenCalledExactlyOnceWith('/version');
    expect(next.text()).toContain('v2.0.0-test');
  });

  it('clears a rejected request so a later mount can retry', async () => {
    mocks.get.mockRejectedValueOnce(new Error('synthetic optional version failure'))
      .mockResolvedValueOnce({ data: { version: '2.0.1-test' } });
    const first = await mountFooter();
    await flushPromises();
    expect(first.text()).not.toContain('2.0.1-test');
    const retry = await mountFooter();
    await flushPromises();
    expect(mocks.get).toHaveBeenCalledTimes(2);
    expect(retry.text()).toContain('v2.0.1-test');
    expect(first.text()).toContain('v2.0.1-test');
  });

  it('does not cache an unsuccessful empty version result', async () => {
    mocks.get.mockResolvedValueOnce({ data: { version: '' } })
      .mockResolvedValueOnce({ data: { version: '2.0.2-test' } });
    await mountFooter();
    await flushPromises();
    const retry = await mountFooter();
    await flushPromises();
    expect(mocks.get).toHaveBeenCalledTimes(2);
    expect(retry.text()).toContain('v2.0.2-test');
  });
});
