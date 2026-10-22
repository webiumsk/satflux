import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TicketsShow from '../pages/stores/TicketsShow.vue';

const mocks = vi.hoisted(() => ({
    fetchTimeZones: vi.fn(), fetchEvents: vi.fn(), createEvent: vi.fn(), updateEvent: vi.fn(),
    fetchTicketTypes: vi.fn(), toggleTicketType: vi.fn(), deleteTicketType: vi.fn(),
    post: vi.fn(), remove: vi.fn(), success: vi.fn(), error: vi.fn(), clear: vi.fn(),
}));
vi.mock('../store/tickets', () => ({ useTicketsStore: () => mocks }));
vi.mock('../store/raffles', () => ({ useRafflesStore: () => ({ raffles: [], fetchAvailability: async () => false }) }));
vi.mock('../store/flash', () => ({ useFlashStore: () => mocks }));
vi.mock('../services/api', () => ({ default: { post: mocks.post, delete: mocks.remove } }));
vi.mock('../components/stores/AppShowLayout.vue', () => ({ default: { template: '<main><slot name="toolbar" :app="$attrs.app" :store="$attrs.store"/><slot/></main>' } }));
vi.mock('../components/stores/UpgradeModal.vue', () => ({ default: { template: '<div />' } }));
vi.mock('../components/admin/RichTextEditor.vue', () => ({ default: { template: '<div />' } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
const event = { id: 'event-a', storeId: 'store-a', title: 'Event', startDate: '2026-11-20T12:00:00Z', eventState: 'Active', eventType: 'Physical', timeZone: 'UTC', hasMaximumCapacity: false };
const ticketType = { id: 'type-a', eventId: event.id, name: 'Standard', price: 5, isDefault: false, ticketTypeState: 'Active' };
const open = async (eventLimit?: number) => {
    const wrapper = mount(TicketsShow, {
        props: { store: { id: 'store-a', name: 'Synthetic store' }, app: { id: '', name: 'Tickets' }, eventLimit } as never,
        global: { stubs: {
            AppShowLayout: { template: '<main><slot name="toolbar" :app="$attrs.app" :store="$attrs.store"/><slot/></main>' },
            DatePicker: true, UpgradeModal: true, UrlQrModal: true, RichTextEditor: true,
        } },
    });
    await flushPromises();
    return wrapper;
};
const button = (wrapper: Awaited<ReturnType<typeof open>>, key: string) => wrapper.findAll('button').find(b => b.text() === key)!;
const select = async (wrapper: Awaited<ReturnType<typeof open>>, file: File) => {
    const zone = wrapper.find('#ticket-event-time-zone');
    if (zone.exists()) {
        await flushPromises();
        await zone.setValue('UTC');
        const start = wrapper.get('#ticket-event-start-local');
        if (!(start.element as HTMLInputElement).value) await start.setValue('2026-11-20T12:00');
    }
    const input = wrapper.get('input[type=file]');
    Object.defineProperty(input.element, 'files', { configurable: true, value: [file] });
    await input.trigger('change');
    await flushPromises();
};
beforeEach(() => {
    vi.resetAllMocks();
    mocks.fetchTimeZones.mockResolvedValue(['UTC', 'Europe/Bratislava']);
    mocks.fetchEvents.mockResolvedValue([]);
    mocks.createEvent.mockResolvedValue(event);
    mocks.updateEvent.mockResolvedValue(event);
    mocks.fetchTicketTypes.mockResolvedValue([ticketType]);
    mocks.post.mockResolvedValue({ data: { data: { eventLogoFileId: 'image-a', eventLogoUrl: 'https://images.example.test/event.png' } } });
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    HTMLElement.prototype.scrollIntoView = vi.fn();
});
describe('ticket event media', () => {
    it('retains a failed image and retries against the saved event without duplicate creation', async () => {
        mocks.fetchEvents.mockResolvedValueOnce([]).mockResolvedValue([event]);
        const wrapper = await open();
        await button(wrapper, 'tickets.create_event').trigger('click');
        await wrapper.get('input[placeholder="tickets.event_title_placeholder"]').setValue('Event');
        const image = new File(['image'], 'event.png', { type: 'image/png' });
        await select(wrapper, image);
        expect(mocks.post).not.toHaveBeenCalled();
        mocks.post.mockRejectedValueOnce(new Error('upstream unavailable'));
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.createEvent).toHaveBeenCalledTimes(1);
        expect(mocks.error).toHaveBeenCalledWith('tickets.event_saved_image_failed');
        expect(mocks.fetchEvents).toHaveBeenCalledTimes(2);
        expect(mocks.error.mock.invocationCallOrder.at(-1)).toBeGreaterThan(mocks.clear.mock.invocationCallOrder.at(-1)!);
        expect(wrapper.find('form').exists()).toBe(true);
        expect(mocks.success).not.toHaveBeenCalled();
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.createEvent).toHaveBeenCalledTimes(1);
        expect(mocks.updateEvent).toHaveBeenCalledWith('store-a', 'event-a', expect.not.objectContaining({ eventLogoFileId: '' }));
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startLocal');
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startDate');
        expect(mocks.post).toHaveBeenCalledTimes(2);
        expect(mocks.post.mock.calls[1][0]).toBe('/stores/store-a/tickets/events/event-a/logo');
        expect((mocks.post.mock.calls[1][1] as FormData).get('file')).toBe(image);
        expect(wrapper.find('form').exists()).toBe(false);
        wrapper.unmount();
    });
    it.each([false, true])('shows a persisted creation and applies its limit after image failure and Cancel (refresh fails: %s)', async refreshFails => {
        mocks.fetchEvents.mockResolvedValueOnce([]);
        if (refreshFails) mocks.fetchEvents.mockRejectedValueOnce(new Error('list temporarily unavailable'));
        else mocks.fetchEvents.mockResolvedValue([event]);
        const wrapper = await open(1);
        await button(wrapper, 'tickets.create_event').trigger('click');
        await wrapper.get('input[placeholder="tickets.event_title_placeholder"]').setValue('Event');
        await select(wrapper, new File(['image'], 'event.png', { type: 'image/png' }));
        mocks.post.mockRejectedValueOnce(new Error('upstream unavailable'));
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.fetchEvents).toHaveBeenCalledTimes(2);
        expect(mocks.error).toHaveBeenLastCalledWith('tickets.event_saved_image_failed');
        await button(wrapper, 'common.cancel').trigger('click');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.findAll('button[title="common.edit"]')).toHaveLength(1);
        await button(wrapper, 'tickets.create_event').trigger('click');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(mocks.createEvent).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
    it.each([
        new File(['<svg/>'], 'event.svg', { type: 'image/svg+xml' }),
        new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'event.png', { type: 'image/png' }),
    ])('rejects unsafe or oversized image selection before uploading', async file => {
        const wrapper = await open(); await button(wrapper, 'tickets.create_event').trigger('click');
        await select(wrapper, file);
        expect(mocks.error).toHaveBeenCalledWith('tickets.image_requirements');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.post).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('waits for Save while editing and never clears a logo through event JSON', async () => {
        mocks.fetchEvents.mockResolvedValue([{ ...event, eventLogoUrl: 'https://images.example.test/old.png', eventLogoFileId: 'old-image' }]);
        const wrapper = await open();
        await wrapper.get('button[title="common.edit"]').trigger('click');
        await select(wrapper, new File(['image'], 'event.png', { type: 'image/png' }));
        expect(mocks.post).not.toHaveBeenCalled();
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.createEvent).not.toHaveBeenCalled();
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('eventLogoUrl');
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('eventLogoFileId');
        expect(mocks.post).toHaveBeenCalledWith('/stores/store-a/tickets/events/event-a/logo', expect.any(FormData));
        wrapper.unmount();
    });
    it('never deletes a persisted image when clearing a replacement and cancelling', async () => {
        mocks.fetchEvents.mockResolvedValue([{ ...event, eventLogoUrl: 'https://images.example.test/old.png', eventLogoFileId: 'old-image' }]);
        const wrapper = await open();
        await wrapper.get('button[title="common.edit"]').trigger('click');
        await select(wrapper, new File(['image'], 'event.png', { type: 'image/png' }));
        await wrapper.get('form button[title="common.delete"]').trigger('click');
        expect(mocks.remove).not.toHaveBeenCalled();
        await button(wrapper, 'common.cancel').trigger('click');
        expect(mocks.remove).not.toHaveBeenCalled();
        expect(mocks.post).not.toHaveBeenCalled();
        expect(mocks.updateEvent).not.toHaveBeenCalled();
        wrapper.unmount();
    });
    it('only deletes the saved image on Save and retains failed removal for retry', async () => {
        mocks.fetchEvents.mockResolvedValue([{ ...event, eventLogoUrl: 'https://images.example.test/old.png', eventLogoFileId: 'old-image' }]);
        const wrapper = await open();
        await wrapper.get('button[title="common.edit"]').trigger('click');
        await wrapper.get('form button[title="common.delete"]').trigger('click');
        expect(mocks.remove).not.toHaveBeenCalled();
        mocks.remove.mockRejectedValueOnce(new Error('temporarily unavailable')).mockResolvedValueOnce({ data: { data: { eventLogoFileId: '', eventLogoUrl: null } } });
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.remove).toHaveBeenCalledTimes(1);
        expect(wrapper.find('form').exists()).toBe(true);
        expect(mocks.success).not.toHaveBeenCalled();
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.remove).toHaveBeenCalledTimes(2);
        expect(mocks.remove).toHaveBeenLastCalledWith('/stores/store-a/tickets/events/event-a/logo');
        expect(mocks.post).not.toHaveBeenCalled();
        expect(wrapper.find('form').exists()).toBe(false);
        wrapper.unmount();
    });
    it('retains the image when a legacy provider omits the uploaded URL', async () => {
        const wrapper = await open(); await button(wrapper, 'tickets.create_event').trigger('click');
        await select(wrapper, new File(['image'], 'event.png', { type: 'image/png' }));
        mocks.post.mockResolvedValueOnce({ data: { data: {} } });
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.error).toHaveBeenCalledWith('tickets.event_saved_image_failed');
        expect(wrapper.find('form').exists()).toBe(true); wrapper.unmount();
    });
});
describe('ticket type lifecycle', () => {
    it.each(['Active', 'Disabled'])('uses the event-scoped toggle and delete from %s then refreshes types', async state => {
        mocks.fetchTicketTypes.mockResolvedValue([{ ...ticketType, ticketTypeState: state }]);
        mocks.fetchEvents.mockResolvedValue([event]);
        const wrapper = await open(); await wrapper.get('.cursor-pointer').trigger('click'); await flushPromises();
        await wrapper.findAll(`button[title="${state === 'Active' ? 'tickets.disable' : 'tickets.activate'}"]`).at(-1)!.trigger('click'); await flushPromises();
        expect(mocks.toggleTicketType).toHaveBeenCalledWith('store-a', 'event-a', 'type-a');
        mocks.fetchTicketTypes.mockResolvedValue([]);
        await wrapper.findAll('button[title="common.delete"]').at(-1)!.trigger('click'); await flushPromises();
        expect(mocks.deleteTicketType).toHaveBeenCalledWith('store-a', 'event-a', 'type-a');
        expect(wrapper.text()).not.toContain('Standard'); wrapper.unmount();
    });
});
