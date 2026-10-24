import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ChoralaPeekLauncher from '../components/feedback/ChoralaPeekLauncher.vue';

const mocks = vi.hoisted(() => ({ open: vi.fn() }));
vi.mock('../services/chorala', () => ({ isChoralaConfigured: () => true, openChoralaWidget: mocks.open }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

beforeEach(() => vi.resetAllMocks());
afterEach(() => { document.body.innerHTML = ''; });

it('shows a translated error and retry control when opening fails, then recovers', async () => {
    mocks.open.mockRejectedValueOnce(new Error('synthetic private detail')).mockResolvedValueOnce(undefined);
    const wrapper = mount(ChoralaPeekLauncher, { attachTo: document.body });
    await flushPromises();
    (document.querySelector('button') as HTMLButtonElement).click();
    await flushPromises();
    expect(document.querySelector('[role=alert]')?.textContent).toContain('common.feedback_unavailable');
    expect(document.body.textContent).not.toContain('synthetic private detail');
    (document.querySelector('[role=alert] button') as HTMLButtonElement).click();
    await flushPromises();
    expect(mocks.open).toHaveBeenCalledTimes(2);
    expect(document.querySelector('[role=alert]')).toBeNull();
    wrapper.unmount();
});

it('shows a safe background refresh failure without exposing the event details', async () => {
    const wrapper = mount(ChoralaPeekLauncher, { attachTo: document.body });
    await flushPromises();
    window.dispatchEvent(new Event('satflux:feedback-unavailable'));
    await flushPromises();
    expect(document.querySelector('[role=alert]')?.textContent).toContain('common.feedback_unavailable');
    wrapper.unmount();
});
