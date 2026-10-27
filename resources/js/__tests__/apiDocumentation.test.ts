import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    auth: { user: null as { id: number; role: string } | null },
    get: vi.fn(),
}));
vi.mock('../store/auth', () => ({ useAuthStore: () => mocks.auth }));
vi.mock('../services/api', () => ({ default: { get: mocks.get } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@scalar/api-reference', () => ({ ApiReference: {
    props: ['configuration'], template: '<div data-reference>{{ JSON.parse(configuration.content).info.title }}</div>',
} }));

import ApiDocumentation from '../pages/admin/ApiDocumentation.vue';
import { apiDocumentationRendererOptions, loadApiDocument, loadApiDocumentationCatalog } from '../services/apiDocumentation';

const document = (id: string) => ({ id, title: id, description: `${id} reference`, url: `/api/admin/api-docs/specs/${id}` });
const catalog = () => ({ documents: ['satflux', 'woocommerce', 'webhooks'].map(document), providers: [{
    id: 'btcpay', title: 'BTCPay', description: 'Provider reference', url: 'https://docs.btcpayserver.org/API/Greenfield/v1/',
}] });
const spec = (title = 'Synthetic Satflux') => ({ openapi: '3.1.0', info: { title, version: '1.0' }, paths: {} });
function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>(yes => { resolve = yes; });
    return { promise, resolve };
}
function mountPage() {
    return mount(ApiDocumentation, { global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } } });
}
enableAutoUnmount(afterEach);
beforeEach(() => {
    vi.clearAllMocks();
    mocks.auth = reactive({ user: { id: 1, role: 'admin' } });
    mocks.get.mockImplementation((path: string) => Promise.resolve({ data: path.endsWith('/catalog') ? catalog() : spec(path) }));
});

describe('private API reference portal', () => {
    it.each([null, { id: 2, role: 'user' }, { id: 3, role: 'guest' }])('does not fetch documents for disallowed account %s', async user => {
        mocks.auth.user = user;
        const wrapper = mountPage();
        await flushPromises();
        expect(mocks.get).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toContain('admin.api_docs.denied');
        expect(wrapper.find('[data-reference]').exists()).toBe(false);
    });
    it.each(['admin', 'support'])('loads local reference for %s and shows separate safe provider links', async role => {
        mocks.auth.user!.role = role;
        const wrapper = mountPage();
        await flushPromises();
        expect(mocks.get.mock.calls.map(call => call[0])).toEqual(['/admin/api-docs/catalog', '/admin/api-docs/specs/satflux']);
        expect(wrapper.get('[data-reference]').text()).toContain('satflux');
        const link = wrapper.find('a[target="_blank"]');
        expect(link.attributes('rel')).toBe('noopener noreferrer');
        expect(link.attributes('href')).toBe('https://docs.btcpayserver.org/API/Greenfield/v1/');
    });
    it('shows loading then recovers after a failed catalog request', async () => {
        const request = deferred<{ data: ReturnType<typeof catalog> }>();
        mocks.get.mockReturnValueOnce(request.promise);
        const wrapper = mountPage();
        expect(wrapper.get('[role="status"]').text()).toBe('admin.api_docs.loading');
        request.resolve({ data: { documents: [], providers: [] } });
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('admin.api_docs.load_error');
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('[data-reference]').exists()).toBe(true);
    });
    it('changes documents and retries a failed spec without showing previous reference', async () => {
        const wrapper = mountPage();
        await flushPromises();
        mocks.get.mockRejectedValueOnce(new Error('Synthetic network failure'));
        await wrapper.get('select').setValue('woocommerce');
        await flushPromises();
        expect(wrapper.find('[data-reference]').exists()).toBe(false);
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-reference]').text()).toContain('woocommerce');
        expect(mocks.get.mock.calls.filter(call => call[0].endsWith('catalog'))).toHaveLength(1);
    });
    it('discards a stale spec after rapid document selection', async () => {
        const wrapper = mountPage();
        await flushPromises();
        const pending = deferred<{ data: ReturnType<typeof spec> }>();
        mocks.get.mockReturnValueOnce(pending.promise);
        await wrapper.get('select').setValue('woocommerce');
        await wrapper.get('select').setValue('webhooks');
        await flushPromises();
        pending.resolve({ data: spec('Stale Woo reference') });
        await flushPromises();
        expect(wrapper.get('[data-reference]').text()).toContain('webhooks');
    });
    it('removes loaded documentation and ignores pending responses after access is lost', async () => {
        const wrapper = mountPage();
        await flushPromises();
        const pending = deferred<{ data: ReturnType<typeof spec> }>();
        mocks.get.mockReturnValueOnce(pending.promise);
        await wrapper.get('select').setValue('webhooks');
        mocks.auth.user = { id: 2, role: 'user' };
        await flushPromises();
        pending.resolve({ data: spec('Stale private reference') });
        await flushPromises();
        expect(wrapper.find('[data-reference]').exists()).toBe(false);
        expect(wrapper.find('select').exists()).toBe(false);
        expect(wrapper.text()).toContain('admin.api_docs.denied');
    });
    it('does not continue a stale catalog request after switching accounts', async () => {
        const pending = deferred<{ data: ReturnType<typeof catalog> }>();
        mocks.get.mockReturnValueOnce(pending.promise);
        const wrapper = mountPage();
        mocks.auth.user = { id: 2, role: 'support' };
        await flushPromises();
        pending.resolve({ data: catalog() });
        await flushPromises();
        expect(mocks.get.mock.calls.filter(call => call[0].endsWith('/specs/satflux'))).toHaveLength(1);
        expect(wrapper.find('[data-reference]').exists()).toBe(true);
    });
});

describe('reference transport boundaries', () => {
    it.each([
        { documents: [{ ...document('satflux'), url: 'https://external.example.test/spec.json' }], providers: [] },
        { documents: [document('satflux'), document('satflux')], providers: [] },
        { documents: [document('private-admin')], providers: [] },
        { ...catalog(), providers: [{ id: 'x', title: 'Injected link', description: '', url: 'javascript:alert(1)' }] },
    ])('rejects a catalog outside the explicit local document and provider allowlist', async data => {
        mocks.get.mockResolvedValueOnce({ data });
        await expect(loadApiDocumentationCatalog()).rejects.toThrow();
    });
    it('rejects external schema refs before the renderer receives a document', async () => {
        mocks.get.mockResolvedValueOnce({ data: { ...spec(), components: { schemas: { Unsafe: { $ref: 'https://external.example.test/schema' } } } } });
        await expect(loadApiDocument('satflux')).rejects.toThrow('External API document references are disabled');
    });
    it('allows local references without performing additional requests', async () => {
        const data = { ...spec(), components: { schemas: { Safe: { $ref: '#/components/schemas/Other' }, Other: { type: 'string' } } } };
        mocks.get.mockResolvedValueOnce({ data });
        expect(await loadApiDocument('satflux')).toEqual(data);
        expect(mocks.get).toHaveBeenCalledTimes(1);
    });
    it('keeps all renderer requests, credentials persistence, telemetry, fonts and AI disabled', async () => {
        const options = apiDocumentationRendererOptions(spec());
        expect(options).toMatchObject({ hideTestRequestButton: true, hideClientButton: true, persistAuth: false, telemetry: false,
            withDefaultFonts: false, agent: { disabled: true }, mcp: { disabled: true }, showDeveloperTools: 'never', proxyUrl: '' });
        await expect(options.customFetch()).rejects.toThrow('API reference requests are disabled');
        expect(mocks.get).not.toHaveBeenCalled();
    });
});
