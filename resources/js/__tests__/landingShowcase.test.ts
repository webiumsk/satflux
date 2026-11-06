import { flushPromises, mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import { ref } from 'vue';
import LandingShowcase from '../pages/LandingShowcase.vue';
import en from '../locales/en.json';
import { LANDING_FEATURE_NAV } from '../constants/landingFeatureNav';
import { readShowcaseDemoUrl, showcaseTools } from '../content/landingShowcase/catalog';

const mocks = vi.hoisted(() => ({
    fetchUserWithOutcome: vi.fn(), loadPricing: vi.fn(), loadFeatures: vi.fn(), loadBtcpay: vi.fn(),
    authenticated: false, pricingLoaded: { value: true }, reducedMotion: false,
}));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ get isAuthenticated() { return mocks.authenticated; }, fetchUserWithOutcome: mocks.fetchUserWithOutcome }) }));
vi.mock('../composables/usePricing', () => ({ usePricing: () => ({ pricing: ref({ trial_days: 30 }), promotionActive: ref(false), loaded: mocks.pricingLoaded, load: mocks.loadPricing }) }));
vi.mock('../composables/usePlanFeatures', () => ({ usePlanFeatures: () => ({ load: mocks.loadFeatures }) }));
vi.mock('../composables/useBtcPayUrl', () => ({ useBtcPayUrl: () => ({ load: mocks.loadBtcpay }) }));
vi.mock('../components/layout/PublicHeader.vue', () => ({ default: { template: '<header>Existing header <a href="/#how-it-works-pos" @click.prevent>PoS</a></header>' } }));
vi.mock('../components/layout/AppFooter.vue', () => ({ default: { template: '<footer>Existing footer</footer>' } }));
vi.mock('../components/account/FreeProNotice.vue', () => ({ default: { template: '<div data-promotion-notice />' } }));

let wrapper: VueWrapper | undefined;
const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
const originalScrollIntoView = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'scrollIntoView');
/** Mount the actual showcase through the established local router/i18n stubs. */
function render(): VueWrapper {
    wrapper = mount(LandingShowcase, {
        attachTo: document.body,
        global: {
            plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })],
            stubs: { RouterLink: RouterLinkStub },
        },
    });
    return wrapper;
}

beforeEach(() => {
    document.body.innerHTML = '<div id="app"></div>';
    window.history.replaceState(null, '', '/');
    vi.clearAllMocks();
    mocks.authenticated = false;
    mocks.pricingLoaded.value = true;
    mocks.reducedMotion = false;
    mocks.fetchUserWithOutcome.mockResolvedValue('guest');
    mocks.loadPricing.mockResolvedValue(undefined);
    mocks.loadFeatures.mockResolvedValue(undefined);
    mocks.loadBtcpay.mockResolvedValue(undefined);
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: mocks.reducedMotion })));
    vi.stubGlobal('requestIdleCallback', vi.fn(() => 1));
    vi.stubGlobal('cancelIdleCallback', vi.fn());
    vi.stubGlobal('requestAnimationFrame', vi.fn(() => 1));
    vi.stubGlobal('cancelAnimationFrame', vi.fn());
    Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', { configurable: true, value: vi.fn() });
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: vi.fn(function (this: HTMLDialogElement) { this.open = true; }) });
    // A later close event reproduces the browser task ordering behind focus races.
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: vi.fn(function (this: HTMLDialogElement) {
        if (!this.open) return;
        this.open = false;
        queueMicrotask(() => this.dispatchEvent(new Event('close')));
    }) });
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    vi.useRealTimers();
    vi.restoreAllMocks();
    if (originalShowModal) Object.defineProperty(HTMLDialogElement.prototype, 'showModal', originalShowModal);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal');
    if (originalClose) Object.defineProperty(HTMLDialogElement.prototype, 'close', originalClose);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, 'close');
    if (originalScrollIntoView) Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', originalScrollIntoView);
    else Reflect.deleteProperty(HTMLElement.prototype, 'scrollIntoView');
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
    document.body.style.overflow = '';
});

describe('showcase public content and interactive flows', () => {
    it('hands the first-paint shell over only after the mounted homepage paints', async () => {
        document.body.innerHTML = '<div id="landing-shell">First paint</div><div id="app" class="sf-app-pending"></div>';
        render();
        await flushPromises();
        expect(document.getElementById('landing-shell')).not.toBeNull();
        expect(document.getElementById('app')!.classList.contains('sf-app-pending')).toBe(true);
        const frame = vi.mocked(requestAnimationFrame).mock.calls[0]![0];
        frame(0);
        expect(document.getElementById('landing-shell')).toBeNull();
        expect(document.getElementById('app')!.classList.contains('sf-app-pending')).toBe(false);
    });

    it('preserves every existing public-header destination and sample disclosure', () => {
        const page = render();
        for (const id of ['clarity', 'features', 'how-it-works', 'pricing', ...LANDING_FEATURE_NAV.map(item => item.id)]) {
            expect(page.find(`#${id}`).exists(), id).toBe(true);
        }
        expect(page.findAll('main img')).toHaveLength(4);
        expect(page.get('.local-first-note').text()).toContain('PDF, email and selected integrations may use server processing');
        expect(page.get('.showcase-caption').text()).toContain('synthetic sample data');
    });

    it('uses keyboard tab navigation without loading an external demo', async () => {
        const page = render();
        expect(page.get('#tab-overview').attributes('aria-selected')).toBe('true');
        await page.get('#tab-overview').trigger('keydown', { key: 'Home' });
        expect(page.get('#tab-live-pos').attributes('aria-selected')).toBe('true');
        expect(document.activeElement?.id).toBe('tab-live-pos');
        expect(page.find('iframe').exists()).toBe(false);
        expect(page.get('.demo-placeholder').text()).toContain('not configured');
        await page.get('#tab-live-pos').trigger('keydown', { key: 'ArrowRight' });
        expect(page.get('#tab-overview').attributes('aria-selected')).toBe('true');
    });

    it('loads a configured HTTPS demo only after the explicit load action', async () => {
        document.getElementById('app')!.dataset.homepageDemoPosUrl = 'https://demo.example.test/pos';
        const page = render();
        await page.get('#tab-live-pos').trigger('click');
        expect(page.find('iframe').exists()).toBe(false);
        await page.get('.demo-placeholder button').trigger('click');
        expect(page.get('iframe').attributes('src')).toBe('https://demo.example.test/pos');
        expect(page.get('iframe').attributes('referrerpolicy')).toBe('no-referrer');
    });

    it('browses eight distinct tools with bounded navigation and restores the original opener', async () => {
        const page = render();
        const opener = page.get('[data-tool=lightning]');
        (opener.element as HTMLButtonElement).focus();
        await opener.trigger('click');
        expect(page.get('#tool-dialog').attributes()).toHaveProperty('open');
        expect(page.get('.tool-nav-button').attributes()).toHaveProperty('disabled');
        for (let i = 0; i < showcaseTools.length; i++) {
            expect(page.get('#tool-detail-title').text()).toBe(en.showcase.tools[showcaseTools[i]!.key].title);
            expect(page.get('#tool-position').text()).toBe(`${i + 1} of 8`);
            if (showcaseTools[i]!.key === 'woocommerce') {
                const docs = page.get('.tool-detail-links a');
                expect(docs.attributes('href')).toBe('https://github.com/webiumsk/satflux-woocommerce/blob/main/readme.txt');
                expect(docs.attributes('rel')).toBe('noopener noreferrer');
            }
            if (showcaseTools[i]!.key === 'paybutton') {
                expect(page.get('.tool-detail-links').getComponent(RouterLinkStub).props('to')).toBe('/documentation/pay-button');
            }
            if (i < showcaseTools.length - 1) await page.get('.tool-nav-next').trigger('click');
        }
        expect(page.get('.tool-nav-next').attributes()).toHaveProperty('disabled');
        await page.get('.tool-detail-back').trigger('click');
        await flushPromises();
        expect(document.activeElement).toBe(opener.element);
        expect(document.body.style.overflow).toBe('');
    });

    it('handles plain tool arrows within bounds and keeps focus on the current heading', async () => {
        const page = render();
        await page.get('[data-tool=lightning]').trigger('click');
        const dialog = page.get('#tool-dialog').element;
        const arrow = async (key: string): Promise<boolean> => {
            const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });
            dialog.dispatchEvent(event);
            await flushPromises();
            return event.defaultPrevented;
        };
        expect(await arrow('ArrowLeft')).toBe(false);
        expect(page.get('#tool-position').text()).toBe('1 of 8');
        for (let i = 1; i < showcaseTools.length; i++) {
            expect(await arrow('ArrowRight')).toBe(true);
            expect(page.get('#tool-position').text()).toBe(`${i + 1} of 8`);
            expect(document.activeElement?.id).toBe('tool-detail-title');
        }
        expect(await arrow('ArrowRight')).toBe(false);
        expect(page.get('#tool-position').text()).toBe('8 of 8');
        expect(await arrow('ArrowLeft')).toBe(true);
        expect(page.get('#tool-position').text()).toBe('7 of 8');
        expect(page.findAll('dialog[open]')).toHaveLength(1);
    });

    it('leaves modified keys and keys on a closed tool dialog unhandled', async () => {
        const page = render();
        await page.get('[data-tool=woocommerce]').trigger('click');
        const dialog = page.get('#tool-dialog').element;
        for (const modifier of ['altKey', 'ctrlKey', 'metaKey', 'shiftKey']) {
            const event = new KeyboardEvent('keydown', { key: 'ArrowRight', [modifier]: true, bubbles: true, cancelable: true });
            dialog.dispatchEvent(event);
            expect(event.defaultPrevented).toBe(false);
        }
        await flushPromises();
        expect(page.get('#tool-position').text()).toBe('2 of 8');
        await page.get('.tool-detail-back').trigger('click');
        await flushPromises();
        const event = new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true, cancelable: true });
        dialog.dispatchEvent(event);
        await flushPromises();
        expect(event.defaultPrevented).toBe(false);
        expect(page.get('#tool-position').text()).toBe('2 of 8');
    });

    it('keeps all-tools focus on the overview heading after the delayed close event', async () => {
        const page = render();
        await page.get('.story .link-button').trigger('click');
        expect(page.get('#detail-dialog').attributes()).toHaveProperty('open');
        await page.get('.dialog-actions button').trigger('click');
        await flushPromises();
        expect(document.activeElement?.id).toBe('ecosystem-title');
        expect(page.get('#detail-dialog').element.hasAttribute('open')).toBe(false);
    });

    it('opens the selected screenshot with fit/native zoom and restores focus on close', async () => {
        const page = render();
        const opener = page.get('.capture-invoicing .capture-window');
        await opener.trigger('click');
        expect(page.get('#zoom-image').attributes('src')).toContain('satflux-invoicing-hover-v6.png');
        expect(page.get('#zoom-size-toggle').attributes('aria-pressed')).toBe('false');
        await page.get('#zoom-size-toggle').trigger('click');
        expect(page.get('.zoom-viewport').classes()).toContain('is-native');
        await page.get('#close-screenshot').trigger('click');
        await flushPromises();
        expect(document.activeElement).toBe(opener.element);
    });
});

describe('public demo URL validation', () => {
    it.each(['', 'http://demo.example.test/pos', '//demo.example.test/pos', 'javascript:alert(1)', 'https://user:secret@demo.example.test/pos', 'not a URL'])('rejects %s without exposing an embed', (url) => {
        document.getElementById('app')!.dataset.homepageDemoPosUrl = url;
        expect(readShowcaseDemoUrl()).toBeNull();
    });
});

/** Create a controlled dependency result without starting any external operation. */
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: unknown) => void;
    const promise = new Promise<T>((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

describe('critical landing outcomes and fragment navigation', () => {
    it('starts auth and pricing without idle, gates CTAs, and resolves pricing independently', async () => {
        const authentication = deferred<'loaded'>();
        const pricing = deferred<void>();
        mocks.fetchUserWithOutcome.mockReturnValueOnce(authentication.promise);
        mocks.loadPricing.mockReturnValueOnce(pricing.promise);
        mocks.pricingLoaded.value = false;
        const page = render();
        expect(mocks.fetchUserWithOutcome).toHaveBeenCalledTimes(1);
        expect(mocks.loadPricing).toHaveBeenCalledTimes(1);
        expect(mocks.loadFeatures).not.toHaveBeenCalled();
        expect(mocks.loadBtcpay).not.toHaveBeenCalled();
        expect(page.find('.actions router-link-stub').exists()).toBe(false);
        expect(page.get('.actions .btn-primary').attributes('disabled')).toBeDefined();
        expect(page.get('.pricing-panel [role=status]').text()).toBe('Loading...');
        mocks.pricingLoaded.value = true;
        pricing.resolve();
        await flushPromises();
        expect(page.find('.pricing-panel [role=status]').exists()).toBe(false);
        expect(page.find('[data-promotion-notice]').exists()).toBe(false);
        expect(page.find('.actions router-link-stub').exists()).toBe(false);
        mocks.authenticated = true;
        authentication.resolve('loaded');
        await flushPromises();
        expect(page.get('.actions').getComponent(RouterLinkStub).props('to')).toBe('/dashboard');
        expect(page.find('[data-promotion-notice]').exists()).toBe(true);
        expect(mocks.loadFeatures).not.toHaveBeenCalled();
    });

    it('offers registration only after a confirmed anonymous result', async () => {
        const authentication = deferred<'guest'>();
        mocks.fetchUserWithOutcome.mockReturnValueOnce(authentication.promise);
        const page = render();
        expect(page.findAllComponents(RouterLinkStub).filter(link => ['/register', '/dashboard'].includes(String(link.props('to'))))).toHaveLength(0);
        authentication.resolve('guest');
        await flushPromises();
        const links = page.findAllComponents(RouterLinkStub).filter(link => link.props('to') === '/register');
        expect(links).toHaveLength(3);
    });

    it('keeps retained auth hidden on unavailable checks and allows a confirmed guest retry', async () => {
        mocks.authenticated = true;
        mocks.fetchUserWithOutcome.mockResolvedValueOnce('unavailable');
        const page = render();
        await flushPromises();
        expect(page.findAllComponents(RouterLinkStub).filter(link => ['/register', '/dashboard'].includes(String(link.props('to'))))).toHaveLength(0);
        expect(page.get('.actions .btn-primary').text()).toBe('Retry');
        mocks.authenticated = false;
        await page.get('.actions .btn-primary').trigger('click');
        await flushPromises();
        expect(mocks.fetchUserWithOutcome).toHaveBeenCalledTimes(2);
        expect(page.get('.actions').getComponent(RouterLinkStub).props('to')).toBe('/register');
    });

    it('keeps rejected auth checks retriable without advertising a guessed destination', async () => {
        mocks.fetchUserWithOutcome.mockRejectedValueOnce(new Error('Synthetic unavailable session check'));
        const page = render();
        await flushPromises();
        expect(page.get('.actions .btn-primary').text()).toBe('Retry');
        expect(page.findAllComponents(RouterLinkStub).filter(link => ['/register', '/dashboard'].includes(String(link.props('to'))))).toHaveLength(0);
    });

    it('waits for a current pricing request even when a cached value is marked loaded', async () => {
        const pricing = deferred<void>();
        mocks.loadPricing.mockReturnValueOnce(pricing.promise);
        const page = render();
        await flushPromises();
        expect(page.get('.pricing-panel [role=status]').text()).toBe('Loading...');
        expect(page.find('[data-promotion-notice]').exists()).toBe(false);
        pricing.reject(new Error('Synthetic failed pricing request'));
        await flushPromises();
        expect(page.get('.pricing-panel button').text()).toBe('Retry');
        expect(page.find('[data-promotion-notice]').exists()).toBe(false);
        await page.get('.pricing-panel button').trigger('click');
        await flushPromises();
        expect(mocks.loadPricing).toHaveBeenCalledTimes(2);
        expect(page.find('.pricing-panel button').exists()).toBe(false);
    });

    it('does not treat the pricing loader fallback as an actual loaded result', async () => {
        mocks.pricingLoaded.value = false;
        const page = render();
        await flushPromises();
        expect(page.get('.pricing-panel button').text()).toBe('Retry');
        expect(page.find('[data-promotion-notice]').exists()).toBe(false);
    });

    it('starts critical loads before the timer fallback for optional work', async () => {
        vi.useFakeTimers();
        vi.stubGlobal('requestIdleCallback', undefined);
        render();
        expect(mocks.fetchUserWithOutcome).toHaveBeenCalledTimes(1);
        expect(mocks.loadPricing).toHaveBeenCalledTimes(1);
        expect(mocks.loadFeatures).not.toHaveBeenCalled();
        await vi.runOnlyPendingTimersAsync();
        expect(mocks.loadFeatures).toHaveBeenCalledTimes(1);
        expect(mocks.loadBtcpay).toHaveBeenCalledTimes(1);
    });

    it('selects the demo for initial and encoded hash changes without loading an iframe', async () => {
        vi.useFakeTimers();
        window.history.replaceState(null, '', '/#how-it-works-pos');
        const page = render();
        await vi.advanceTimersByTimeAsync(100);
        expect(page.get('#tab-live-pos').attributes('aria-selected')).toBe('true');
        expect(page.find('iframe').exists()).toBe(false);
        await page.get('#tab-overview').trigger('click');
        window.history.replaceState(null, '', '/#%68ow-it-works-pos');
        window.dispatchEvent(new HashChangeEvent('hashchange'));
        await flushPromises();
        expect(page.get('#tab-live-pos').attributes('aria-selected')).toBe('true');
        expect(page.find('iframe').exists()).toBe(false);
    });

    it('cancels queued fragment navigation when the homepage unmounts', async () => {
        vi.useFakeTimers();
        const page = render();
        const link = document.createElement('a');
        link.href = '/#pricing';
        link.addEventListener('click', event => event.preventDefault());
        page.get('header').element.append(link);
        const scheduled = vi.spyOn(globalThis, 'setTimeout');
        const cleared = vi.spyOn(globalThis, 'clearTimeout');
        link.click();
        const queuedIndex = scheduled.mock.calls.findIndex(([, delay]) => delay === 0);
        expect(queuedIndex).toBeGreaterThanOrEqual(0);
        const fragmentTask = scheduled.mock.results[queuedIndex]!.value;
        await flushPromises();
        expect(HTMLElement.prototype.scrollIntoView).not.toHaveBeenCalled();
        page.unmount();
        wrapper = undefined;
        expect(cleared).toHaveBeenCalledWith(fragmentTask);
        await vi.runAllTimersAsync();
        expect(HTMLElement.prototype.scrollIntoView).not.toHaveBeenCalled();
    });

    it.each([false, true])('reuses the reduced-motion preference for every scroll entry (reduce=%s)', async reduce => {
        vi.useFakeTimers();
        mocks.reducedMotion = reduce;
        const page = render();
        await page.get('.actions .btn-secondary').trigger('click');
        expect(HTMLElement.prototype.scrollIntoView).toHaveBeenLastCalledWith({ behavior: reduce ? 'instant' : 'smooth', block: 'center' });
        await page.get('.story .link-button').trigger('click');
        await page.get('.dialog-actions button').trigger('click');
        expect(HTMLElement.prototype.scrollIntoView).toHaveBeenLastCalledWith({ behavior: reduce ? 'instant' : 'smooth', block: 'start' });
        await page.get('#tab-overview').trigger('click');
        await page.get('header a').trigger('click');
        await vi.advanceTimersByTimeAsync(0);
        await flushPromises();
        expect(page.get('#tab-live-pos').attributes('aria-selected')).toBe('true');
        expect(page.find('iframe').exists()).toBe(false);
        expect(HTMLElement.prototype.scrollIntoView).toHaveBeenLastCalledWith({ behavior: reduce ? 'instant' : 'smooth', block: 'start' });

        // The actual header scrolls synchronously after the page's capture handler.
        const pricingLink = document.createElement('a');
        pricingLink.href = '/#pricing';
        pricingLink.addEventListener('click', event => {
            event.preventDefault();
            document.getElementById('pricing')!.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        page.get('header').element.append(pricingLink);
        vi.mocked(HTMLElement.prototype.scrollIntoView).mockClear();
        pricingLink.click();
        expect(HTMLElement.prototype.scrollIntoView).toHaveBeenLastCalledWith({ behavior: 'smooth', block: 'start' });
        await flushPromises();
        // Promise/Vue microtasks are insufficient to finish native click propagation.
        expect(HTMLElement.prototype.scrollIntoView).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(0);
        await flushPromises();
        expect(HTMLElement.prototype.scrollIntoView).toHaveBeenCalledTimes(2);
        expect(HTMLElement.prototype.scrollIntoView).toHaveBeenLastCalledWith({ behavior: reduce ? 'instant' : 'smooth', block: 'start' });
    });
});
