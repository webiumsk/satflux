import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    auth: { user: null as { id: number } | null },
}));
vi.mock('../services/api', () => ({ default: { get: mocks.get } }));
vi.mock('../i18n', () => ({ default: { global: { locale: { value: 'en' } } } }));
vi.mock('../store/auth', () => ({ useAuthStore: () => mocks.auth }));

type Service = typeof import('../services/chorala');
let service: Service;
const listeners: Array<{ target: EventTarget; type: string; listener: EventListenerOrEventListenerObject }> = [];

async function tick(): Promise<void> {
    for (let i = 0; i < 12; i += 1) await Promise.resolve();
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;
    const promise = new Promise<T>((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

function token(jwt = 'synthetic-token') {
    return { data: { jwt, expires_at: Math.floor(Date.now() / 1000) + 3600 } };
}

async function completeScript(supportsRefresh = true): Promise<HTMLScriptElement> {
    await tick();
    const script = document.querySelector<HTMLScriptElement>('script[data-chorala-key]');
    expect(script).not.toBeNull();
    const queue = window.Chorala?.q ?? [];
    const sdk = vi.fn();
    for (const args of queue) sdk(...args);
    window.Chorala = Object.assign(sdk, { supportsIdentityRefresh: supportsRefresh });
    script!.dispatchEvent(new Event('load'));
    await tick();
    return script!;
}

beforeEach(async () => {
    vi.resetModules();
    vi.resetAllMocks();
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-24T12:00:00Z'));
    document.head.innerHTML = '<meta name="satflux-chorala-key" content="pk_synthetic"><meta name="satflux-chorala-widget-url" content="https://feedback.example.test"><meta name="satflux-chorala-use-proxy" content="true">';
    delete window.Chorala;
    mocks.auth.user = { id: 1 };
    mocks.get.mockImplementation((path: string) => Promise.resolve(path.endsWith('widget-settings') ? { data: { settings: {} } } : token()));
    const windowAdd = window.addEventListener.bind(window);
    vi.spyOn(window, 'addEventListener').mockImplementation((type, listener, options) => {
        if (listener) listeners.push({ target: window, type, listener });
        windowAdd(type, listener, options);
    });
    const documentAdd = document.addEventListener.bind(document);
    vi.spyOn(document, 'addEventListener').mockImplementation((type, listener, options) => {
        if (listener) listeners.push({ target: document, type, listener });
        documentAdd(type, listener, options);
    });
    service = await import('../services/chorala');
});

afterEach(() => {
    for (const { target, type, listener } of listeners.splice(0)) target.removeEventListener(type, listener);
    vi.clearAllTimers();
    vi.useRealTimers();
    vi.restoreAllMocks();
    delete window.Chorala;
});

describe('Chorala direct transport and identity', () => {
    it('uses the configured direct API even when an old page advertises proxy=true', async () => {
        const open = service.openChoralaWidget();
        await completeScript();
        await open;
        expect(document.querySelector<HTMLScriptElement>('script[data-chorala-key]')?.src).not.toContain('https://feedback.example.test');
        expect(window.Chorala).toHaveBeenCalledWith('init', expect.objectContaining({ apiUrl: 'https://feedback.example.test/api/v1' }));
        expect(window.Chorala).toHaveBeenCalledWith('identify', { jwt: 'synthetic-token' });
        expect(window.Chorala).toHaveBeenLastCalledWith('open');
        expect(mocks.get.mock.calls.every(([path]) => !String(path).includes('chorala-proxy'))).toBe(true);
    });

    it('allows anonymous visitors to open without fetching an identity token', async () => {
        mocks.auth.user = null;
        const open = service.openChoralaWidget();
        await completeScript();
        await open;
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(0);
        expect(window.Chorala).toHaveBeenLastCalledWith('open');
    });

    it('deduplicates concurrent identity requests and reuses a current token', async () => {
        const first = service.syncChoralaIdentity();
        const second = service.syncChoralaIdentity();
        await completeScript();
        await Promise.all([first, second]);
        await service.syncChoralaIdentity();
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(1);
    });

    it('retains anonymous feedback when optional SSO is explicitly unconfigured', async () => {
        mocks.get.mockImplementation((path: string) => path.endsWith('widget-settings')
            ? Promise.resolve({ data: { settings: {} } })
            : Promise.reject({ response: { status: 404, data: { message: 'Chorala widget SSO is not configured.' } } }));
        const open = service.openChoralaWidget();
        await completeScript();
        await open;
        expect(window.Chorala).toHaveBeenLastCalledWith('open');
        expect(window.Chorala).not.toHaveBeenCalledWith('identify', expect.objectContaining({ jwt: expect.anything() }));
    });

    it('refreshes before one hour without recreating the widget or opening it', async () => {
        const sync = service.syncChoralaIdentity();
        await completeScript();
        await sync;
        await vi.advanceTimersByTimeAsync(55 * 60_000);
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(2);
        expect(document.querySelectorAll('script[data-chorala-key]')).toHaveLength(1);
        expect(vi.mocked(window.Chorala!).mock.calls.filter(([command, payload]) => command === 'identify' && (payload as { jwt?: string })?.jwt)).toHaveLength(1);
        expect(window.Chorala).toHaveBeenCalledWith('refreshIdentity', { jwt: 'synthetic-token' });
        expect(window.Chorala).not.toHaveBeenCalledWith('open');
        expect(vi.mocked(window.Chorala!).mock.calls.filter(([command]) => command === 'init')).toHaveLength(1);
    });

    it('refreshes an expired identity on focus after a suspended tab', async () => {
        const sync = service.syncChoralaIdentity();
        await completeScript();
        await sync;
        vi.setSystemTime(new Date('2026-10-24T13:10:00Z'));
        window.dispatchEvent(new Event('focus'));
        await tick();
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(2);
    });

    it('reschedules an early callback after the wall clock moves backwards', async () => {
        const sync = service.syncChoralaIdentity();
        await completeScript();
        await sync;
        vi.setSystemTime(Date.now() - 1);
        await vi.advanceTimersByTimeAsync(55 * 60_000);
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(1);
        expect(vi.getTimerCount()).toBe(1);
        await vi.advanceTimersByTimeAsync(1000);
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(2);
        expect(window.Chorala).toHaveBeenLastCalledWith('refreshIdentity', { jwt: 'synthetic-token' });
        expect(vi.getTimerCount()).toBe(1);
    });

    it('keeps rechecking optional SSO after an early callback without recreating the widget', async () => {
        mocks.get.mockImplementation((path: string) => path.endsWith('widget-settings')
            ? Promise.resolve({ data: { settings: {} } })
            : Promise.reject({ response: { status: 404, data: { message: 'Chorala widget SSO is not configured.' } } }));
        const open = service.openChoralaWidget();
        await completeScript();
        await open;
        const sdk = window.Chorala;
        vi.setSystemTime(Date.now() - 60_000);
        await vi.advanceTimersByTimeAsync(10 * 60_000);
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(1);
        expect(vi.getTimerCount()).toBe(1);
        await vi.advanceTimersByTimeAsync(60_000);
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(2);
        await vi.advanceTimersByTimeAsync(10 * 60_000);
        expect(mocks.get.mock.calls.filter(([path]) => path === '/chorala/widget-token')).toHaveLength(3);
        expect(window.Chorala).toBe(sdk);
        expect(sdk).toHaveBeenLastCalledWith('refreshIdentity', {});
        expect(vi.mocked(sdk!).mock.calls.filter(([command]) => command === 'init')).toHaveLength(1);
        expect(vi.mocked(sdk!).mock.calls.filter(([command]) => command === 'open')).toHaveLength(1);
        expect(vi.getTimerCount()).toBe(1);
    });

    it('preserves the same-user client on a transient refresh failure and recovers without identify', async () => {
        const open = service.openChoralaWidget();
        await completeScript();
        await open;
        const sdk = window.Chorala;
        const callsBeforeFailure = vi.mocked(sdk!).mock.calls.length;
        vi.setSystemTime(new Date('2026-10-24T13:10:00Z'));
        mocks.get.mockRejectedValueOnce(new Error('temporary refresh failure'));
        await service.syncChoralaIdentity();
        expect(window.Chorala).toBe(sdk);
        expect(vi.mocked(sdk!).mock.calls).toHaveLength(callsBeforeFailure);
        mocks.get.mockResolvedValueOnce(token('recovered-token'));
        await vi.advanceTimersByTimeAsync(60_000);
        expect(sdk).toHaveBeenLastCalledWith('refreshIdentity', { jwt: 'recovered-token' });
        expect(vi.mocked(sdk!).mock.calls.filter(([command, payload]) => command === 'identify' && (payload as { jwt?: string })?.jwt)).toHaveLength(1);
    });

    it('removes a JWT without rebuilding when SSO is explicitly disabled for the same user', async () => {
        const sync = service.syncChoralaIdentity();
        await completeScript();
        await sync;
        vi.setSystemTime(new Date('2026-10-24T13:10:00Z'));
        mocks.get.mockRejectedValueOnce({ response: { status: 404, data: { message: 'Chorala widget SSO is not configured.' } } });
        await service.syncChoralaIdentity();
        expect(window.Chorala).toHaveBeenLastCalledWith('refreshIdentity', {});
        expect(window.Chorala).not.toHaveBeenCalledWith('identify', {});
    });

    it('fails safely when a loaded widget does not support nondestructive refresh', async () => {
        const open = service.openChoralaWidget();
        const rejected = expect(open).rejects.toThrow('Feedback could not be loaded.');
        await completeScript(false);
        await rejected;
        expect(window.Chorala).not.toHaveBeenCalledWith('open');
        expect(window.Chorala).not.toHaveBeenCalledWith('identify', expect.anything());
    });

    it('ignores the previous account token after an account switch', async () => {
        const previous = deferred<ReturnType<typeof token>>();
        let tokenCalls = 0;
        mocks.get.mockImplementation((path: string) => {
            if (path.endsWith('widget-settings')) return Promise.resolve({ data: { settings: {} } });
            tokenCalls += 1;
            return tokenCalls === 1 ? previous.promise : Promise.resolve(token('account-b-token'));
        });
        const first = service.syncChoralaIdentity();
        await completeScript();
        mocks.auth.user = { id: 2 };
        await service.syncChoralaIdentity();
        previous.resolve(token('account-a-token'));
        await first;
        expect(window.Chorala).toHaveBeenCalledWith('identify', { jwt: 'account-b-token' });
        expect(window.Chorala).not.toHaveBeenCalledWith('identify', { jwt: 'account-a-token' });
    });

    it('clears identity and rejects a late token after logout', async () => {
        const pending = deferred<ReturnType<typeof token>>();
        mocks.get.mockImplementation((path: string) => path.endsWith('widget-settings') ? Promise.resolve({ data: { settings: {} } }) : pending.promise);
        const sync = service.syncChoralaIdentity();
        await completeScript();
        mocks.auth.user = null;
        await service.syncChoralaIdentity();
        pending.resolve(token('logged-out-token'));
        await sync;
        expect(window.Chorala).toHaveBeenCalledWith('identify', {});
        expect(window.Chorala).not.toHaveBeenCalledWith('identify', { jwt: 'logged-out-token' });
        expect(vi.getTimerCount()).toBe(0);
    });

    it('keeps a rejected identity visible and allows opening to retry', async () => {
        mocks.get.mockImplementation((path: string) => path.endsWith('widget-settings') ? Promise.resolve({ data: { settings: {} } }) : Promise.reject(new Error('synthetic rejection')));
        const event = vi.fn();
        window.addEventListener('satflux:feedback-unavailable', event);
        const open = service.openChoralaWidget();
        const rejected = expect(open).rejects.toThrow('Feedback identity could not be refreshed.');
        await completeScript();
        await rejected;
        expect(event).toHaveBeenCalledOnce();
        expect(window.Chorala).not.toHaveBeenCalledWith('open');
        mocks.get.mockResolvedValue(token());
        await service.openChoralaWidget();
        expect(window.Chorala).toHaveBeenLastCalledWith('open');
    });

    it('times out a blocked script, removes it, and allows a fresh retry', async () => {
        const open = service.openChoralaWidget();
        const rejected = expect(open).rejects.toThrow('Feedback could not be loaded.');
        await tick();
        await vi.advanceTimersByTimeAsync(20_000);
        await rejected;
        expect(document.querySelector('script[data-chorala-key]')).toBeNull();
        const retry = service.openChoralaWidget();
        await completeScript();
        await retry;
        expect(window.Chorala).toHaveBeenLastCalledWith('open');
    });
});
