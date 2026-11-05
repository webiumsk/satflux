import { flushPromises, mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import { ref } from 'vue';
import LandingShowcase from '../pages/LandingShowcase.vue';
import en from '../locales/en.json';
import { LANDING_FEATURE_NAV } from '../constants/landingFeatureNav';
import { readShowcaseDemoUrl, showcaseTools } from '../content/landingShowcase/catalog';

const mocks = vi.hoisted(() => ({ fetchUser: vi.fn().mockResolvedValue(undefined), load: vi.fn().mockResolvedValue(undefined) }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ isAuthenticated: false, fetchUser: mocks.fetchUser }) }));
vi.mock('../composables/usePricing', () => ({ usePricing: () => ({ pricing: ref({ trial_days: 30 }), promotionActive: ref(false), load: mocks.load }) }));
vi.mock('../composables/usePlanFeatures', () => ({ usePlanFeatures: () => ({ load: mocks.load }) }));
vi.mock('../composables/useBtcPayUrl', () => ({ useBtcPayUrl: () => ({ load: mocks.load }) }));
vi.mock('../components/layout/PublicHeader.vue', () => ({ default: { template: '<header>Existing header</header>' } }));
vi.mock('../components/layout/AppFooter.vue', () => ({ default: { template: '<footer>Existing footer</footer>' } }));
vi.mock('../components/account/FreeProNotice.vue', () => ({ default: { template: '<div />' } }));

let wrapper: VueWrapper | undefined;
const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
const originalScrollIntoView = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'scrollIntoView');
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
