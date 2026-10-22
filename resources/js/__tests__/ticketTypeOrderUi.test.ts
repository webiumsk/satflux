import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TicketsShow from '../pages/stores/TicketsShow.vue';

const mocks = vi.hoisted(() => ({
    fetchEvents: vi.fn(), fetchTicketTypes: vi.fn(), fetchTicketTypeOrder: vi.fn(), saveTicketTypeOrder: vi.fn(),
    toggleTicketType: vi.fn(), deleteTicketType: vi.fn(), success: vi.fn(), error: vi.fn(), clear: vi.fn(),
}));
vi.mock('../store/tickets', () => ({ useTicketsStore: () => mocks }));
vi.mock('../store/raffles', () => ({ useRafflesStore: () => ({ raffles: [], fetchAvailability: async () => false }) }));
vi.mock('../store/flash', () => ({ useFlashStore: () => mocks }));
vi.mock('../components/stores/AppShowLayout.vue', () => ({ default: { template: '<main><slot name="toolbar" :app="$attrs.app" :store="$attrs.store"/><slot/></main>' } }));
vi.mock('../components/stores/UpgradeModal.vue', () => ({ default: { template: '<div />' } }));
vi.mock('../components/admin/RichTextEditor.vue', () => ({ default: { template: '<div />' } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string, params?: Record<string, unknown>) => params?.name ? `${key}:${params.name}` : key }) }));
enableAutoUnmount(afterEach);
const event = { id: 'event-a', storeId: 'local-store', title: 'Synthetic event', startDate: '2026-11-20T12:00:00Z', eventState: 'Active', eventType: 'Physical', hasMaximumCapacity: false };
const types = [
    { id: 'type-a', eventId: event.id, name: 'Standard', price: 5, isDefault: true, ticketTypeState: 'Active' },
    { id: 'type-b', eventId: event.id, name: 'Early', price: 3, isDefault: false, ticketTypeState: 'Disabled' },
];
async function open() {
    const wrapper = mount(TicketsShow, {
        props: { store: { id: 'local-store', name: 'Synthetic store' }, app: { id: '', name: 'Tickets' } } as never,
        global: { stubs: { DatePicker: true, UpgradeModal: true, UrlQrModal: true, RichTextEditor: true } },
        attachTo: document.body,
    });
    await flushPromises();
    await wrapper.get('.cursor-pointer').trigger('click'); await flushPromises();
    return wrapper;
}
const button = (wrapper: Awaited<ReturnType<typeof open>>, key: string) => wrapper.findAll('button').find(b => b.text() === key)!;
const rowIds = (wrapper: Awaited<ReturnType<typeof open>>) => wrapper.findAll('[data-ticket-type-id]').map(row => row.attributes('data-ticket-type-id'));
beforeEach(() => {
    vi.resetAllMocks();
    mocks.fetchEvents.mockResolvedValue([event]); mocks.fetchTicketTypes.mockResolvedValue(types);
    mocks.fetchTicketTypeOrder.mockResolvedValue({ ticketTypeIds: ['type-a', 'type-b'], revision: 7 });
    mocks.saveTicketTypeOrder.mockResolvedValue({ ticketTypeIds: ['type-b', 'type-a'], revision: 8 });
});
describe('inline order controls in existing ticket types tab', () => {
    it('supports labelled keyboard/touch movement, preserves existing actions, and saves all types', async () => {
        const wrapper = await open();
        expect(mocks.fetchTicketTypeOrder).not.toHaveBeenCalled();
        await button(wrapper, 'tickets.order_edit').trigger('click'); await flushPromises();
        expect(wrapper.findAll('input,form')).toHaveLength(0);
        expect(wrapper.get('[data-order-handle="type-a"]').element).toBe(document.activeElement);
        expect(wrapper.find('button[title="common.edit"][disabled]').exists()).toBe(true);
        await wrapper.get('[aria-label="tickets.order_up:Early"]').trigger('click');
        expect(rowIds(wrapper)).toEqual(['type-b', 'type-a']);
        expect(wrapper.get('[role="status"]').text()).toBe('tickets.order_moved:Early');
        await button(wrapper, 'tickets.order_save').trigger('click'); await flushPromises();
        expect(mocks.saveTicketTypeOrder).toHaveBeenCalledWith('local-store', 'event-a', { ticketTypeIds: ['type-b', 'type-a'], revision: 7 });
        expect(rowIds(wrapper)).toEqual(['type-b', 'type-a']);
        expect(wrapper.find('[data-order-handle]').exists()).toBe(false);
        expect(wrapper.find('button[title="tickets.activate"]').exists()).toBe(true);
        wrapper.unmount();
    });
    it('supports drag and drop only for an internally started drag and Cancel restores saved order', async () => {
        const wrapper = await open(); await button(wrapper, 'tickets.order_edit').trigger('click'); await flushPromises();
        await wrapper.get('[data-ticket-type-id="type-b"]').trigger('drop');
        expect(rowIds(wrapper)).toEqual(['type-a', 'type-b']);
        await wrapper.get('[data-order-handle="type-a"]').trigger('dragstart', { dataTransfer: { effectAllowed: '', setData: vi.fn() } });
        await wrapper.get('[data-ticket-type-id="type-b"]').trigger('drop');
        expect(rowIds(wrapper)).toEqual(['type-b', 'type-a']);
        await button(wrapper, 'common.cancel').trigger('click');
        expect(rowIds(wrapper)).toEqual(['type-a', 'type-b']);
        expect(mocks.saveTicketTypeOrder).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('keeps draft visible on409 and requires an explicit reload before any retry', async () => {
        const wrapper = await open(); await button(wrapper, 'tickets.order_edit').trigger('click'); await flushPromises();
        await wrapper.get('[data-order-handle="type-b"]').trigger('keydown', { key: 'ArrowUp', altKey: true });
        mocks.saveTicketTypeOrder.mockRejectedValueOnce({ response: { status: 409 } });
        await button(wrapper, 'tickets.order_save').trigger('click'); await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('tickets.order_error_conflict');
        expect(rowIds(wrapper)).toEqual(['type-b', 'type-a']);
        expect(button(wrapper, 'tickets.order_save').attributes('disabled')).toBeDefined();
        mocks.fetchTicketTypeOrder.mockResolvedValueOnce({ ticketTypeIds: ['type-a', 'type-b'], revision: 9 });
        await button(wrapper, 'tickets.order_reload').trigger('click'); await flushPromises();
        expect(rowIds(wrapper)).toEqual(['type-a', 'type-b']);
        expect(mocks.saveTicketTypeOrder).toHaveBeenCalledTimes(1); wrapper.unmount();
    });
    it('blocks event collapse and protects dirty drafts from browser close', async () => {
        const wrapper = await open(); await button(wrapper, 'tickets.order_edit').trigger('click'); await flushPromises();
        await wrapper.get('[aria-label="tickets.order_down:Standard"]').trigger('click');
        await wrapper.get('.cursor-pointer').trigger('click');
        expect(wrapper.find('[data-order-handle]').exists()).toBe(true);
        expect(mocks.error).toHaveBeenCalledWith('tickets.order_finish_first');
        const event = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(true);
        await button(wrapper, 'common.cancel').trigger('click');
        const afterCancel = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(afterCancel);
        expect(afterCancel.defaultPrevented).toBe(false); wrapper.unmount();
    });
    it('preserves ordinary management with old plugin and never fakes successful order saving', async () => {
        const wrapper = await open();
        mocks.fetchTicketTypeOrder.mockRejectedValueOnce({ response: { status: 404 } });
        await button(wrapper, 'tickets.order_edit').trigger('click'); await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe('tickets.order_error_upgrade');
        expect(wrapper.find('[data-order-handle]').exists()).toBe(false);
        expect(button(wrapper, 'tickets.add_ticket_type')).toBeDefined();
        expect(wrapper.get('button[title="tickets.activate"]').attributes('disabled')).toBeUndefined();
        expect(mocks.saveTicketTypeOrder).not.toHaveBeenCalled(); wrapper.unmount();
    });
});
