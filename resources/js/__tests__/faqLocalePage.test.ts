import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { defineComponent, nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const state = vi.hoisted(() => ({
  locale: undefined as unknown as { value: string }, index: vi.fn(), helpful: vi.fn(),
}));
vi.mock('vue-i18n', async () => {
  const actual = await vi.importActual<typeof import('vue-i18n')>('vue-i18n');
  const { ref } = await import('vue');
  state.locale = ref('en');
  return { ...actual, useI18n: () => ({ locale: state.locale, t: (key: string) => `${state.locale.value}:${key}` }) };
});
vi.mock('../services/api', () => ({ faqApi: { index: state.index, markHelpful: state.helpful } }));
vi.mock('../components/layout/PublicHeader.vue', () => ({ default: { template: '<header />' } }));
vi.mock('../components/layout/AppFooter.vue', () => ({ default: { template: '<footer />' } }));
import FaqPage from '../pages/faq/Index.vue';

const AccordionStub = defineComponent({
  name: 'FaqAccordion', props: ['items', 'searchQuery'], emits: ['helpful'],
  template: '<section data-test="answers"><p v-for="item in items" :key="item.id" :data-id="item.id">{{ item.question }} {{ item.answer }}</p></section>',
});
const CategoryStub = defineComponent({
  name: 'CategoryFilter', props: ['modelValue', 'categories'], emits: ['update:modelValue', 'filter'],
  template: '<select :value="modelValue || \'\'" @change="$emit(\'update:modelValue\', $event.target.value); $emit(\'filter\', $event.target.value)"><option value="">All</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>',
});

/** Return a locale-specific list with stable synthetic FAQ/category identifiers. */
const response = (locale: string) => ({ data: {
  data: [{ id: 'faq-shared-id', slug: 'synthetic-faq', question: `${locale} shared question`, answer: `${locale} shared answer` }],
  categories: [{ id: 'category-shared-id', name: `${locale} category` }],
} });

/** Control completion order without introducing network requests or real credentials. */
const deferred = <T,>() => {
  let resolve!: (value: T) => void;
  let reject!: (error: unknown) => void;
  const promise = new Promise<T>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
};

/** Mount the real public page with lightweight presentational boundary stubs. */
const mountPage = () => mount(FaqPage, {
  global: { stubs: { FaqAccordion: AccordionStub, CategoryFilter: CategoryStub } },
});

enableAutoUnmount(afterEach);
beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
  vi.clearAllMocks();
  state.locale.value = 'en';
  state.index.mockImplementation((params?: { locale?: string }) => Promise.resolve(response(params?.locale || 'en')));
  state.helpful.mockResolvedValue({ data: {} });
});
afterEach(() => { vi.restoreAllMocks(); vi.useRealTimers(); });

describe('public FAQ locale requests', () => {
  it('sends the active Slovak locale on the first request', async () => {
    state.locale.value = 'sk';
    const wrapper = mountPage();
    await flushPromises();
    expect(state.index.mock.calls[0][0]).toMatchObject({ locale: 'sk' });
    expect(wrapper.get('[data-test=answers]').text()).toContain('sk shared question');
    expect(wrapper.get('select').text()).toContain('sk category');
  });

  it('reloads all five locale contents and category labels without changing identifiers', async () => {
    const wrapper = mountPage();
    await flushPromises();
    for (const locale of ['en', 'sk', 'es', 'de', 'cs']) {
      state.locale.value = locale;
      await nextTick();
      await flushPromises();
      expect(state.index.mock.calls.at(-1)![0]).toMatchObject({ locale });
      expect(wrapper.get('[data-test=answers]').text()).toContain(`${locale} shared answer`);
      expect(wrapper.get('select').text()).toContain(`${locale} category`);
      expect(wrapper.get('[data-id]').attributes('data-id')).toBe('faq-shared-id');
    }
    expect(state.index).toHaveBeenCalledTimes(5);
  });

  it('ignores superseded success/error/finalizers while the newest locale is loading', async () => {
    const requests = { en: deferred<ReturnType<typeof response>>(), sk: deferred<ReturnType<typeof response>>(), de: deferred<ReturnType<typeof response>>() };
    state.index.mockImplementation((params: { locale: keyof typeof requests }) => requests[params.locale].promise);
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    const wrapper = mountPage();
    state.locale.value = 'sk';
    await nextTick();
    state.locale.value = 'de';
    await nextTick();
    expect(state.index).toHaveBeenCalledTimes(3);
    expect(state.index.mock.calls[0][1].signal.aborted).toBe(true);
    expect(state.index.mock.calls[1][1].signal.aborted).toBe(true);
    requests.sk.resolve(response('sk'));
    requests.en.reject(new Error('Synthetic superseded failure'));
    await flushPromises();
    expect(wrapper.find('[data-test=answers]').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('sk category');
    expect(error).not.toHaveBeenCalled();
    requests.de.resolve(response('de'));
    await flushPromises();
    expect(wrapper.get('[data-test=answers]').text()).toContain('de shared question');
    expect(wrapper.get('select').text()).toContain('de category');
  });

  it('keeps the latest locale after older requests complete later', async () => {
    const requests = { en: deferred<ReturnType<typeof response>>(), sk: deferred<ReturnType<typeof response>>(), de: deferred<ReturnType<typeof response>>() };
    state.index.mockImplementation((params: { locale: keyof typeof requests }) => requests[params.locale].promise);
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    const wrapper = mountPage();
    state.locale.value = 'sk'; await nextTick();
    state.locale.value = 'de'; await nextTick();
    requests.de.resolve(response('de'));
    await flushPromises();
    requests.en.reject(new Error('Synthetic old error'));
    requests.sk.resolve(response('sk'));
    await flushPromises();
    expect(wrapper.get('[data-test=answers]').text()).toContain('de shared answer');
    expect(wrapper.get('select').text()).toContain('de category');
    expect(error).not.toHaveBeenCalled();
  });

  it('preserves search and category while a locale switch replaces a pending debounce', async () => {
    const wrapper = mountPage();
    await flushPromises();
    await wrapper.get('select').setValue('category-shared-id');
    await flushPromises();
    await wrapper.get('input').setValue('shared');
    state.locale.value = 'sk';
    await nextTick(); await flushPromises();
    expect(state.index.mock.calls.at(-1)![0]).toEqual({ locale: 'sk', category_id: 'category-shared-id', search: 'shared' });
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('shared');
    expect((wrapper.get('select').element as HTMLSelectElement).value).toBe('category-shared-id');
    expect(wrapper.get('select').text()).toContain('sk category');
    await vi.advanceTimersByTimeAsync(500);
    expect(state.index).toHaveBeenCalledTimes(3);
  });

  it('debounces search and Enter into one request without duplicate category fetches', async () => {
    const wrapper = mountPage(); await flushPromises();
    await wrapper.get('input').setValue('sha');
    await wrapper.get('input').setValue('shared');
    await wrapper.get('input').trigger('keyup.enter');
    expect(state.index).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(300); await flushPromises();
    expect(state.index).toHaveBeenCalledTimes(2);
    expect(state.index.mock.calls.at(-1)![0]).toMatchObject({ locale: 'en', search: 'shared' });
    await wrapper.get('select').setValue('category-shared-id'); await flushPromises();
    await vi.advanceTimersByTimeAsync(500);
    expect(state.index).toHaveBeenCalledTimes(3);
  });

  it('cancels a queued debounce on unmount', async () => {
    const wrapper = mountPage(); await flushPromises();
    await wrapper.get('input').setValue('shared');
    wrapper.unmount();
    await vi.advanceTimersByTimeAsync(500);
    state.locale.value = 'sk'; await nextTick();
    expect(state.index).toHaveBeenCalledTimes(1);
  });

  it('aborts and ignores pending requests on unmount', async () => {
    const pending = deferred<ReturnType<typeof response>>();
    state.index.mockReturnValue(pending.promise);
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    const wrapper = mountPage();
    const signal = state.index.mock.calls[0][1].signal as AbortSignal;
    wrapper.unmount();
    expect(signal.aborted).toBe(true);
    pending.reject(new Error('Synthetic completion after unmount'));
    await flushPromises();
    expect(error).not.toHaveBeenCalled();
  });

  it.each(['resolve', 'reject'])('invalidates old %s callbacks before the search debounce starts a replacement', async settlement => {
    const pending = deferred<ReturnType<typeof response>>();
    state.index.mockReturnValueOnce(pending.promise);
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    const wrapper = mountPage();
    await wrapper.get('input').setValue('shared');
    if (settlement === 'resolve') pending.resolve(response('old'));
    else pending.reject(new Error('Synthetic pre-debounce failure'));
    await flushPromises();
    expect(wrapper.find('[data-test=answers]').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('old category');
    expect(error).not.toHaveBeenCalled();
    expect(state.index).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(300); await flushPromises();
    expect(state.index).toHaveBeenCalledTimes(2);
    expect(wrapper.get('[data-test=answers]').text()).toContain('en shared question');
  });

  it.each([
    [undefined, 'errors.network'], [500, 'errors.server_error'], [429, 'errors.rate_limited'],
  ])('shows current request failure %s locally and retries without changing locale or filters', async (status, key) => {
    state.locale.value = 'sk';
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    const wrapper = mountPage(); await flushPromises();
    await wrapper.get('select').setValue('category-shared-id'); await flushPromises();
    await wrapper.get('input').setValue('shared');
    state.index.mockRejectedValueOnce({ isAxiosError: true, ...(status === undefined ? {} : { response: { status } }) });
    await vi.advanceTimersByTimeAsync(300); await flushPromises();
    const params = { locale: 'sk', category_id: 'category-shared-id', search: 'shared' };
    expect(state.index.mock.calls.at(-1)![0]).toEqual(params);
    expect(state.index.mock.calls.at(-1)![1]).toMatchObject({ skipErrorFlash: true });
    expect(wrapper.get('[role=alert]').text()).toContain(`sk:${key}`);
    expect(wrapper.text()).not.toContain('faq.no_items_found');
    await wrapper.get('[role=alert] button').trigger('click'); await flushPromises();
    expect(state.index.mock.calls.at(-1)![0]).toEqual(params);
    expect(wrapper.find('[role=alert]').exists()).toBe(false);
    expect(wrapper.get('[data-test=answers]').text()).toContain('sk shared question');
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('shared');
    expect((wrapper.get('select').element as HTMLSelectElement).value).toBe('category-shared-id');
    expect(error).toHaveBeenCalledTimes(1);
  });

  it('starts rapid locale replacements synchronously before any promise completion can apply', async () => {
    const requests = { en: deferred<ReturnType<typeof response>>(), sk: deferred<ReturnType<typeof response>>(), de: deferred<ReturnType<typeof response>>() };
    state.index.mockImplementation((params: { locale: keyof typeof requests }) => requests[params.locale].promise);
    const wrapper = mountPage();
    state.locale.value = 'sk';
    state.locale.value = 'de';
    expect(state.index.mock.calls.map(call => call[0].locale)).toEqual(['en', 'sk', 'de']);
    requests.en.resolve(response('en')); requests.sk.resolve(response('sk'));
    await flushPromises();
    expect(wrapper.find('[data-test=answers]').exists()).toBe(false);
    requests.de.resolve(response('de')); await flushPromises();
    expect(wrapper.get('[data-test=answers]').text()).toContain('de shared answer');
  });

  it('forwards list/detail locale parameters through the actual API helper', async () => {
    const actual = await vi.importActual<typeof import('../services/api')>('../services/api');
    const get = vi.spyOn(actual.default, 'get').mockResolvedValue({ data: {} });
    const signal = new AbortController().signal;
    await actual.faqApi.index({ locale: 'sk', search: 'shared' }, { signal, skipErrorFlash: true });
    await actual.faqApi.show('synthetic-faq', { locale: 'de' });
    expect(get).toHaveBeenNthCalledWith(1, '/faq', { params: { locale: 'sk', search: 'shared' }, signal, skipErrorFlash: true });
    expect(get).toHaveBeenNthCalledWith(2, '/faq/synthetic-faq', { params: { locale: 'de' } });
  });
});
