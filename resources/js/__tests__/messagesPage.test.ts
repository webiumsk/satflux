import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({ get: vi.fn(), patch: vi.fn(), post: vi.fn(), push: vi.fn() }));
vi.mock('../services/api', () => ({ default: { get: mocks.get, patch: mocks.patch, post: mocks.post } }));
vi.mock('vue-router', () => ({ useRouter: () => ({ push: mocks.push }) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
import Messages from '../pages/Messages.vue';

type MessageFixture = {
    id: string; type: string; category: 'critical' | 'invoices' | 'other'; title: string;
    body: string; link: string | null; link_text: string | null; read_at: string | null; created_at: string;
};
const message = (overrides: Partial<MessageFixture> = {}): MessageFixture => ({
    id: 'synthetic-notification', type: 'invoicestate', category: 'invoices', title: 'Synthetic invoice settled',
    body: 'An invoice in the synthetic store was settled.', link: '/stores/local-synthetic-uuid/invoices/invoice-synthetic',
    link_text: null, read_at: null, created_at: '2026-10-28T13:25:00Z', ...overrides,
});
function response(items: MessageFixture[] = [message()], extraMeta = {}) {
    return { data: { data: items, meta: { current_page: 1, last_page: 1, per_page: 20, total: items.length, ...extraMeta }, available: true } };
}
function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>(yes => { resolve = yes; });
    return { promise, resolve };
}
const mountPage = () => mount(Messages);
const listCalls = () => mocks.get.mock.calls.filter(call => call[0] === '/messages');
const cursorError = () => ({ isAxiosError: true, response: { status: 422, data: { errors: { cursor: ['Synthetic expired cursor'] } } } });
enableAutoUnmount(afterEach);
beforeEach(() => {
    vi.clearAllMocks();
    mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count' ? { data: { data: { unread: 1 } } } : response()));
    mocks.patch.mockResolvedValue({ data: {} });
    mocks.post.mockResolvedValue({ data: {} });
    mocks.push.mockResolvedValue(undefined);
});

describe('message feed usability', () => {
    it('shows full local date/time and retains server chronology rather than pinning security', async () => {
        mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count' ? { data: { data: { unread: 1 } } } : response([
            message(), message({ id: 'sf_1', type: 'security', category: 'critical', title: 'Older security alert', created_at: '2026-10-20T07:04:00Z', read_at: '2026-10-21T08:00:00Z', link: null }),
        ])));
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.findAll('article h3').map(node => node.text())).toEqual(['Synthetic invoice settled', 'Older security alert']);
        const date = new Date('2026-10-28T13:25:00Z');
        expect(wrapper.get('time').text()).toBe(`28.10.2026 ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`);
        expect(wrapper.get('time').attributes('datetime')).toBe('2026-10-28T13:25:00Z');
        expect(wrapper.findAll('article')[0].text()).toContain('messages.unread');
        expect(wrapper.findAll('article')[1].text()).toContain('messages.read');
        expect(wrapper.findAll('article')[1].text()).toContain('messages.security_label');
    });

    it('combines server category and unread filters and resets to page one', async () => {
        const wrapper = mountPage();
        await flushPromises();
        expect(listCalls()[0][1].params).toMatchObject({ page: 1, category: 'all', unread: 0 });
        await wrapper.get('select').setValue('invoices');
        await flushPromises();
        await wrapper.get('input[type=checkbox]').setValue(true);
        await flushPromises();
        expect(listCalls().at(-1)![1].params).toMatchObject({ page: 1, category: 'invoices', unread: 1 });
    });

    it('continues a sparse filtered scan and can return through its cursor history', async () => {
        mocks.get.mockImplementation((path: string, options?: { params: { cursor?: string } }) => Promise.resolve(path === '/messages/count'
            ? { data: { data: { unread: 1 } } }
            : options?.params.cursor ? response([message()], { current_page: 2, has_more: false, next_cursor: null })
                : response([], { has_more: true, next_cursor: 'synthetic-cursor-one', scan_limited: true })));
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.text()).toContain('messages.scan_empty');
        expect(wrapper.text()).not.toContain('messages.empty_title');
        const next = wrapper.findAll('button').find(button => button.text() === 'messages.continue_search')!;
        await next.trigger('click');
        await flushPromises();
        expect(listCalls().at(-1)![1].params).toMatchObject({ page: 2, cursor: 'synthetic-cursor-one' });
        expect(wrapper.find('article').exists()).toBe(true);
        const previous = wrapper.findAll('button').find(button => button.text() === 'messages.previous')!;
        await previous.trigger('click');
        await flushPromises();
        expect(listCalls().at(-1)![1].params).toMatchObject({ page: 1 });
        expect(listCalls().at(-1)![1].params).not.toHaveProperty('cursor');
    });

    it.each(['all', 'invoices'])('keeps Previous available on an empty terminal continuation page for %s messages', async category => {
        mocks.get.mockImplementation((path: string, options?: { params: { cursor?: string } }) => Promise.resolve(path === '/messages/count'
            ? { data: { data: { unread: 1 } } }
            : options?.params.cursor ? response([], { current_page: 2, has_more: false, next_cursor: null })
                : response([message()], { has_more: true, next_cursor: 'terminal-synthetic-cursor' })));
        const wrapper = mountPage();
        await flushPromises();
        if (category !== 'all') {
            await wrapper.get('select').setValue(category);
            await flushPromises();
        }
        await wrapper.findAll('button').find(button => button.text() === 'messages.next')!.trigger('click');
        await flushPromises();
        expect(listCalls().at(-1)![1].params).toMatchObject({ page: 2, cursor: 'terminal-synthetic-cursor', category });
        expect(wrapper.find('article').exists()).toBe(false);
        const previous = wrapper.findAll('button').find(button => button.text() === 'messages.previous');
        expect(previous).toBeDefined();
        expect(previous!.attributes('disabled')).toBeUndefined();
        await previous!.trigger('click');
        await flushPromises();
        expect(listCalls().at(-1)![1].params).toMatchObject({ page: 1, category });
        expect(listCalls().at(-1)![1].params).not.toHaveProperty('cursor');
        expect(wrapper.get('article h3').text()).toBe('Synthetic invoice settled');
    });

    it('discards cursor history when the category or unread filter changes', async () => {
        mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count'
            ? { data: { data: { unread: 1 } } }
            : response([message()], { has_more: true, next_cursor: 'synthetic-cursor-one' })));
        const wrapper = mountPage();
        await flushPromises();
        await wrapper.findAll('button').find(button => button.text() === 'messages.next')!.trigger('click');
        await flushPromises();
        expect(listCalls().at(-1)![1].params.cursor).toBe('synthetic-cursor-one');
        await wrapper.get('select').setValue('critical');
        await flushPromises();
        expect(listCalls().at(-1)![1].params).toMatchObject({ page: 1, category: 'critical' });
        expect(listCalls().at(-1)![1].params).not.toHaveProperty('cursor');
    });

    it('recovers an expired continuation cursor with one fresh first-page request', async () => {
        mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count'
            ? { data: { data: { unread: 1 } } }
            : response([message()], { has_more: true, next_cursor: 'expired-synthetic-cursor' })));
        const wrapper = mountPage();
        await flushPromises();
        mocks.get.mockRejectedValueOnce(cursorError());
        await wrapper.findAll('button').find(button => button.text() === 'messages.next')!.trigger('click');
        await flushPromises();
        expect(listCalls()).toHaveLength(3);
        expect(listCalls()[1][1].params).toMatchObject({ page: 2, cursor: 'expired-synthetic-cursor' });
        expect(listCalls()[2][1].params).toMatchObject({ page: 1 });
        expect(listCalls()[2][1].params).not.toHaveProperty('cursor');
        expect(wrapper.find('[role=alert]').exists()).toBe(false);
        expect(wrapper.find('article').exists()).toBe(true);
    });

    it('does not loop on cursor validation errors from a first-page request', async () => {
        mocks.get.mockImplementation((path: string) => path === '/messages/count'
            ? Promise.resolve({ data: { data: { unread: 1 } } })
            : Promise.reject(cursorError()));
        const wrapper = mountPage();
        await flushPromises();
        expect(listCalls()).toHaveLength(1);
        expect(wrapper.get('[role=alert]').text()).toContain('messages.load_error');
    });

    it('does not reset a newly selected filter after a stale cursor request fails', async () => {
        mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count'
            ? { data: { data: { unread: 1 } } }
            : response([message()], { has_more: true, next_cursor: 'old-filter-cursor' })));
        const wrapper = mountPage();
        await flushPromises();
        let rejectPending!: (reason: unknown) => void;
        mocks.get.mockReturnValueOnce(new Promise((_resolve, reject) => { rejectPending = reject; }));
        await wrapper.findAll('button').find(button => button.text() === 'messages.next')!.trigger('click');
        await wrapper.get('select').setValue('critical');
        await flushPromises();
        const callsBeforeStaleFailure = listCalls().length;
        rejectPending(cursorError());
        await flushPromises();
        expect(listCalls()).toHaveLength(callsBeforeStaleFailure);
        expect(listCalls().at(-1)![1].params).toMatchObject({ page: 1, category: 'critical' });
        expect(wrapper.get('select').element.value).toBe('critical');
        expect(wrapper.find('[role=alert]').exists()).toBe(false);
    });

    it('discards a late request from a previously selected category', async () => {
        const wrapper = mountPage();
        await flushPromises();
        const pending = deferred<ReturnType<typeof response>>();
        mocks.get.mockReturnValueOnce(pending.promise);
        await wrapper.get('select').setValue('critical');
        await wrapper.get('select').setValue('other');
        await flushPromises();
        pending.resolve(response([message({ title: 'Stale security response' })]));
        await flushPromises();
        expect(wrapper.text()).not.toContain('Stale security response');
        expect(wrapper.get('select').element.value).toBe('other');
    });

    it('shows a filtered empty state rather than implying there are no messages', async () => {
        const wrapper = mountPage();
        await flushPromises();
        mocks.get.mockResolvedValueOnce(response([]));
        await wrapper.get('select').setValue('critical');
        await flushPromises();
        expect(wrapper.text()).toContain('messages.filtered_empty_title');
        expect(wrapper.text()).not.toContain('messages.empty_title');
        expect(wrapper.find('select').exists()).toBe(true);
    });

    it('shows load errors and retries without retaining the previous list', async () => {
        const wrapper = mountPage();
        await flushPromises();
        mocks.get.mockRejectedValueOnce(new Error('Synthetic failure'));
        await wrapper.get('select').setValue('critical');
        await flushPromises();
        expect(wrapper.find('article').exists()).toBe(false);
        expect(wrapper.get('[role=alert]').text()).toContain('messages.load_error');
        await wrapper.get('[role=alert] button').trigger('click');
        await flushPromises();
        expect(wrapper.find('article').exists()).toBe(true);
        expect(listCalls().at(-1)![1].params.category).toBe('critical');
    });

    it('does not let count availability hide local security messages', async () => {
        mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count'
            ? { data: { available: false, data: { unread: 0 } } }
            : response([message({ id: 'sf_2', category: 'critical', type: 'security', read_at: '2026-10-28T14:00:00Z' })])));
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.find('article').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('messages.not_available_title');
    });

    it('uses a native button to mark a message read without opening its destination', async () => {
        const wrapper = mountPage();
        await flushPromises();
        await wrapper.get('article button').trigger('click');
        await flushPromises();
        expect(mocks.patch).toHaveBeenCalledExactlyOnceWith('/messages/synthetic-notification/read');
        expect(mocks.push).not.toHaveBeenCalled();
        expect(wrapper.get('article').text()).toContain('messages.read');
        expect(wrapper.find('article button').exists()).toBe(false);
    });

    it('keeps the unread badge and exposes a retryable error if marking fails', async () => {
        mocks.patch.mockRejectedValueOnce(new Error('Synthetic failure'));
        const wrapper = mountPage();
        await flushPromises();
        await wrapper.get('article button').trigger('click');
        await flushPromises();
        expect(wrapper.get('[role=alert]').text()).toContain('messages.read_error');
        expect(wrapper.get('article').text()).toContain('messages.unread');
        expect(wrapper.get('article button').attributes('disabled')).toBeUndefined();
    });

    it('removes a read message from the unread filter using confirmed server state', async () => {
        const wrapper = mountPage();
        await flushPromises();
        await wrapper.get('input[type=checkbox]').setValue(true);
        await flushPromises();
        mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count'
            ? { data: { data: { unread: 0 } } } : response([])));
        await wrapper.get('article button').trigger('click');
        await flushPromises();
        expect(wrapper.find('article').exists()).toBe(false);
        expect(wrapper.text()).toContain('messages.filtered_empty_title');
        expect(listCalls().at(-1)![1].params.unread).toBe(1);
    });

    it('uses an owned-store relative invoice destination within the SPA', async () => {
        const wrapper = mountPage();
        await flushPromises();
        const link = wrapper.get('article a');
        expect(link.attributes('href')).toBe('/stores/local-synthetic-uuid/invoices/invoice-synthetic');
        expect(link.text()).toBe('messages.view_invoice');
        await link.trigger('click');
        await flushPromises();
        expect(mocks.push).toHaveBeenCalledWith('/stores/local-synthetic-uuid/invoices/invoice-synthetic');
        expect(mocks.patch).toHaveBeenCalled();
    });

    it.each(['javascript:alert(1)', 'data:text/html,synthetic', '//external.example.test/path', '/\\external.example.test/path', 'https://user:password@example.test/path'])('does not render or navigate unsafe link %s', async link => {
        mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count' ? { data: { data: { unread: 1 } } } : response([message({ link })])));
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.find('article a').exists()).toBe(false);
        expect(mocks.push).not.toHaveBeenCalled();
    });

    it('preserves safe external destinations with opener isolation', async () => {
        mocks.get.mockImplementation((path: string) => Promise.resolve(path === '/messages/count' ? { data: { data: { unread: 1 } } } : response([message({ link: 'https://explorer.example.test/tx/synthetic' })])));
        const open = vi.spyOn(window, 'open').mockImplementation(() => null);
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.get('article a').attributes('rel')).toBe('noopener noreferrer');
        await wrapper.get('article a').trigger('click');
        expect(open).toHaveBeenCalledWith('https://explorer.example.test/tx/synthetic', '_blank', 'noopener,noreferrer');
        open.mockRestore();
    });

    it('reloads confirmed state after marking all rather than claiming failed remote changes succeeded', async () => {
        const wrapper = mountPage();
        await flushPromises();
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(mocks.post).toHaveBeenCalledExactlyOnceWith('/messages/mark-all-read');
        expect(listCalls()).toHaveLength(2);
        expect(wrapper.get('article').text()).toContain('messages.unread');
    });
});
