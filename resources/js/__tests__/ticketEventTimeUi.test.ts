import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TicketsShow from '../pages/stores/TicketsShow.vue';

const mocks = vi.hoisted(() => ({
    fetchEvents: vi.fn(), fetchTimeZones: vi.fn(), createEvent: vi.fn(), updateEvent: vi.fn(),
    success: vi.fn(), error: vi.fn(), clear: vi.fn(),
}));
vi.mock('../store/tickets', () => ({ useTicketsStore: () => mocks }));
vi.mock('../store/raffles', () => ({ useRafflesStore: () => ({ raffles: [], fetchAvailability: async () => false }) }));
vi.mock('../store/flash', () => ({ useFlashStore: () => mocks }));
vi.mock('../components/stores/AppShowLayout.vue', () => ({ default: { template: '<main><slot name="toolbar" :app="$attrs.app" :store="$attrs.store"/><slot/></main>' } }));
vi.mock('../components/stores/UpgradeModal.vue', () => ({ default: { template: '<div />' } }));
vi.mock('../components/admin/RichTextEditor.vue', () => ({ default: { template: '<div />' } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string, params?: Record<string, unknown>) => params?.zone ? `${key}:${params.zone}` : key }) }));
const event = {
    id: 'event-a', storeId: 'local-store', title: 'Synthetic event', startDate: '2026-11-28T07:00:42.123456Z', endDate: '2026-11-29T10:00:37.456789Z',
    eventState: 'Active', eventType: 'Physical', timeZone: 'Europe/Bratislava', hasMaximumCapacity: false,
};
enableAutoUnmount(afterEach);
const zones = ['UTC', 'Europe/Bratislava', 'America/New_York', 'Europe/Vienna'];
async function open(create = false, storeTimeZone?: string) {
    const wrapper = mount(TicketsShow, {
        props: { store: { id: 'local-store', name: 'Synthetic store', timezone: storeTimeZone }, app: { id: '', name: 'Tickets' } } as never,
        global: { stubs: {
            DatePicker: { props: ['modelValue'], emits: ['update:modelValue'], template: '<input class="synthetic-date-picker" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
            UpgradeModal: true, UrlQrModal: true, RichTextEditor: true,
        } },
        attachTo: document.body,
    });
    await flushPromises();
    if (create) await button(wrapper, 'tickets.create_event').trigger('click');
    else await wrapper.get('button[title="common.edit"]').trigger('click');
    await flushPromises();
    return wrapper;
}
const button = (wrapper: ReturnType<typeof mount>, key: string) => wrapper.findAll('button').find(b => b.text() === key)!;
const submit = async (wrapper: ReturnType<typeof mount>) => { await wrapper.get('form').trigger('submit'); await flushPromises(); };
beforeEach(() => {
    vi.resetAllMocks();
    mocks.fetchEvents.mockResolvedValue([event]); mocks.fetchTimeZones.mockResolvedValue(zones);
    mocks.updateEvent.mockResolvedValue(event); mocks.createEvent.mockResolvedValue(event);
    HTMLElement.prototype.scrollIntoView = vi.fn();
});
describe('existing event editor venue times', () => {
    it('shows venue clock text and omits unchanged UTC dates including subsecond precision', async () => {
        const wrapper = await open();
        expect((wrapper.get('#ticket-event-start-local').element as HTMLInputElement).value).toBe('2026-11-28T08:00');
        expect(wrapper.findAll('.synthetic-date-picker')).toHaveLength(0);
        expect(wrapper.text()).toContain('Europe/Bratislava');
        await wrapper.get('input[placeholder="tickets.event_title_placeholder"]').setValue('Changed title');
        await submit(wrapper);
        const data = mocks.updateEvent.mock.calls[0][2];
        expect(data).toMatchObject({ title: 'Changed title', timeZone: 'Europe/Bratislava' });
        for (const key of ['startDate', 'endDate', 'startLocal', 'endLocal']) expect(data).not.toHaveProperty(key);
    });
    it('reformats clean dates on zone-only changes but does not write any new instant', async () => {
        const wrapper = await open(); await wrapper.get('#ticket-event-time-zone').setValue('America/New_York');
        expect((wrapper.get('#ticket-event-start-local').element as HTMLInputElement).value).toBe('2026-11-28T02:00');
        await submit(wrapper);
        expect(mocks.updateEvent.mock.calls[0][2]).toMatchObject({ timeZone: 'America/New_York' });
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startLocal');
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startDate');
    });
    it('keeps edited civil time on zone changes and lets the provider resolve its UTC instant', async () => {
        const wrapper = await open();
        await wrapper.get('#ticket-event-start-local').setValue('2026-03-08T02:30');
        await wrapper.get('#ticket-event-time-zone').setValue('America/New_York');
        expect((wrapper.get('#ticket-event-start-local').element as HTMLInputElement).value).toBe('2026-03-08T02:30');
        await submit(wrapper);
        expect(mocks.updateEvent.mock.calls[0][2]).toMatchObject({ timeZone: 'America/New_York', startLocal: '2026-03-08T02:30' });
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startDate');
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('endLocal');
    });
    it.each(['nonexistent', 'ambiguous'])('shows provider DST %s errors inline, keeps the draft, and permits correction', async reason => {
        const wrapper = await open(); await wrapper.get('#ticket-event-start-local').setValue('2026-10-25T02:30');
        const code = `event-start-local-${reason}`;
        mocks.updateEvent.mockRejectedValueOnce({ response: { status: 422, data: { code, errors: { startLocal: [code] } } } });
        await submit(wrapper);
        expect(wrapper.get('#ticket-event-start-error').text()).toBe(`tickets.event_time_error_${reason === 'ambiguous' ? 'overlap' : 'gap'}`);
        expect((wrapper.get('#ticket-event-start-local').element as HTMLInputElement).value).toBe('2026-10-25T02:30');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
        expect(mocks.success).not.toHaveBeenCalled();
        await wrapper.get('#ticket-event-start-local').setValue('2026-11-28T09:00'); await submit(wrapper);
        expect(mocks.updateEvent).toHaveBeenCalledTimes(2);
    });
    it('clears a previously saved end with explicit endLocal:null', async () => {
        const wrapper = await open(); await wrapper.get('#ticket-event-end-local').setValue(''); await submit(wrapper);
        expect(mocks.updateEvent.mock.calls[0][2]).toMatchObject({ endLocal: null });
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('endDate');
    });
    it.each([404, 502])('blocks writes when the provider catalog fails with%s and retains draft for retry', async status => {
        mocks.fetchTimeZones.mockRejectedValueOnce({ response: { status } });
        const wrapper = await open(); await wrapper.get('input[placeholder="tickets.event_title_placeholder"]').setValue('Keep my draft');
        await submit(wrapper);
        expect(mocks.updateEvent).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain(status === 404 ? 'tickets.event_time_zone_upgrade' : 'tickets.event_time_zone_load_error');
        expect((wrapper.get('input[placeholder="tickets.event_title_placeholder"]').element as HTMLInputElement).value).toBe('Keep my draft');
        await button(wrapper, 'tickets.event_time_zone_retry').trigger('click'); await flushPromises(); await submit(wrapper);
        expect(mocks.updateEvent).toHaveBeenCalledTimes(1);
    });
    it('does not infer a legacy missing venue zone and selecting one preserves the original instant', async () => {
        mocks.fetchEvents.mockResolvedValue([{ ...event, timeZone: null }]);
        const wrapper = await open();
        expect(wrapper.text()).toContain('tickets.event_time_missing_label');
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('');
        expect((wrapper.get('#ticket-event-start-local').element as HTMLInputElement).value).toBe('2026-11-28T07:00');
        await submit(wrapper); expect(mocks.updateEvent).not.toHaveBeenCalled();
        await wrapper.get('#ticket-event-time-zone').setValue('Europe/Bratislava'); await submit(wrapper);
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startLocal');
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startDate');
    });
    it('requires an explicit provider catalog zone on new physical events and submits civil time unchanged', async () => {
        const wrapper = await open(true);
        await wrapper.get('input[placeholder="tickets.event_title_placeholder"]').setValue('New event');
        await wrapper.get('#ticket-event-start-local').setValue('2026-03-08T02:30'); await submit(wrapper);
        expect(mocks.createEvent).not.toHaveBeenCalled();
        await wrapper.get('#ticket-event-time-zone').setValue('Europe/Bratislava'); await submit(wrapper);
        expect(mocks.createEvent.mock.calls[0][1]).toMatchObject({ startLocal: '2026-03-08T02:30', timeZone: 'Europe/Bratislava' });
        expect(mocks.createEvent.mock.calls[0][1]).not.toHaveProperty('startDate');
    });
    it('uses browser-local virtual fields without rewriting instants on a type-only change', async () => {
        const wrapper = await open(); await button(wrapper, 'tickets.virtual').trigger('click');
        expect(wrapper.find('#ticket-event-time-zone').exists()).toBe(false);
        expect(wrapper.findAll('.synthetic-date-picker')).toHaveLength(2);
        expect(wrapper.text()).toContain('tickets.event_time_virtual_hint:');
        await submit(wrapper);
        const data = mocks.updateEvent.mock.calls[0][2];
        expect(data.eventType).toBe('Virtual');
        for (const key of ['timeZone', 'startDate', 'endDate', 'startLocal', 'endLocal']) expect(data).not.toHaveProperty(key);
    });
    it('blocks unsupported stored zone metadata safely with explicit UTC display', async () => {
        const invalid = 'Invalid/Not_A_Zone';
        mocks.fetchEvents.mockResolvedValue([{ ...event, timeZone: invalid }]);
        mocks.fetchTimeZones.mockResolvedValue([...zones, invalid]);
        const wrapper = await open();
        expect((wrapper.get('#ticket-event-start-local').element as HTMLInputElement).value).toBe('2026-11-28T07:00');
        expect(wrapper.text()).toContain('tickets.event_time_missing_label');
        await submit(wrapper); expect(mocks.updateEvent).not.toHaveBeenCalled();
        expect(wrapper.get('#ticket-event-zone-error').text()).toBe('tickets.event_time_zone_choose');
    });
    it('cleans up busy state after invalid virtual datetime conversion', async () => {
        const wrapper = await open(); await button(wrapper, 'tickets.virtual').trigger('click');
        await wrapper.findAll('.synthetic-date-picker')[0].setValue('not-a-date'); await submit(wrapper);
        expect(mocks.updateEvent).not.toHaveBeenCalled();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
        expect(mocks.error).toHaveBeenCalled();
    });
});


describe('Store time zone defaults for new physical events', () => {
    it.each(['Europe/Vienna', 'UTC'])('snapshots the valid local Store default %s with per-event override', async zone => {
        const wrapper = await open(true, zone);
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe(zone);
        await wrapper.get('input[placeholder="tickets.event_title_placeholder"]').setValue('New event');
        await wrapper.get('#ticket-event-start-local').setValue('2026-11-28T08:00');
        await wrapper.get('#ticket-event-time-zone').setValue('Europe/Bratislava');
        await submit(wrapper);
        expect(mocks.createEvent.mock.calls[0][1]).toMatchObject({ timeZone: 'Europe/Bratislava', startLocal: '2026-11-28T08:00' });
    });
    it.each([undefined, '', 'Unknown/Not_A_Zone', 'Pacific/Auckland'])('does not invent a default for unknown or unavailable Store setting %s', async zone => {
        const wrapper = await open(true, zone);
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('');
        await wrapper.get('#ticket-event-start-local').setValue('2026-11-28T08:00'); await submit(wrapper);
        expect(mocks.createEvent).not.toHaveBeenCalled();
    });
    it('leaves an unsupported browser zone unset even if it appears in the provider catalog', async () => {
        const zone = 'Unknown/Not_A_Zone';
        mocks.fetchTimeZones.mockResolvedValue([...zones, zone]);
        const wrapper = await open(true, zone);
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('');
    });
    it('does not apply a cancelled new-form default to an existing event after a late catalog response', async () => {
        let finish!: (zones: string[]) => void;
        mocks.fetchTimeZones.mockReturnValueOnce(new Promise(resolve => { finish = resolve; }));
        const wrapper = await open(true, 'Europe/Vienna');
        await button(wrapper, 'common.cancel').trigger('click');
        await wrapper.get('button[title="common.edit"]').trigger('click'); await flushPromises();
        finish(zones); await flushPromises();
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('Europe/Bratislava');
        await submit(wrapper);
        expect(mocks.updateEvent.mock.calls[0][2]).toMatchObject({ timeZone: 'Europe/Bratislava' });
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startLocal');
    });
    it('never replaces an existing event zone with the Store default, including later Store changes', async () => {
        const wrapper = await open(false, 'Europe/Vienna');
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('Europe/Bratislava');
        await wrapper.setProps({ store: { id: 'local-store', name: 'Synthetic store', timezone: 'UTC' } } as never);
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('Europe/Bratislava');
        await submit(wrapper);
        expect(mocks.updateEvent.mock.calls[0][2]).toMatchObject({ timeZone: 'Europe/Bratislava' });
        expect(mocks.updateEvent.mock.calls[0][2]).not.toHaveProperty('startLocal');
    });
    it('does not replace an explicit event choice when a Store default arrives later', async () => {
        const wrapper = await open(true);
        await wrapper.get('#ticket-event-time-zone').setValue('America/New_York');
        await wrapper.setProps({ store: { id: 'local-store', name: 'Synthetic store', timezone: 'Europe/Vienna' } } as never);
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('America/New_York');
        await wrapper.get('#ticket-event-start-local').setValue('2026-11-28T08:00'); await submit(wrapper);
        expect(mocks.createEvent.mock.calls[0][1]).toMatchObject({ timeZone: 'America/New_York' });
    });
    it('does not inject a late catalog default after the user has entered a civil time', async () => {
        let finish!: (zones: string[]) => void;
        mocks.fetchTimeZones.mockReturnValueOnce(new Promise(resolve => { finish = resolve; }));
        const wrapper = await open(true, 'Europe/Vienna');
        await wrapper.get('#ticket-event-start-local').setValue('2026-11-28T08:00');
        finish(zones); await flushPromises();
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('');
        expect((wrapper.get('#ticket-event-start-local').element as HTMLInputElement).value).toBe('2026-11-28T08:00');
    });
    it('does not inject a late default after event type changes', async () => {
        let finish!: (zones: string[]) => void;
        mocks.fetchTimeZones.mockReturnValueOnce(new Promise(resolve => { finish = resolve; }));
        const wrapper = await open(true, 'Europe/Vienna');
        await button(wrapper, 'tickets.virtual').trigger('click');
        await button(wrapper, 'tickets.physical').trigger('click');
        finish(zones); await flushPromises();
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('');
    });
    it('uses a changed Store default only for the next new event, including the cached catalog path', async () => {
        const wrapper = await open(true, 'Europe/Vienna');
        await wrapper.setProps({ store: { id: 'local-store', name: 'Synthetic store', timezone: 'UTC' } } as never);
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('Europe/Vienna');
        await button(wrapper, 'common.cancel').trigger('click');
        await button(wrapper, 'tickets.create_event').trigger('click'); await flushPromises();
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('UTC');
        expect(mocks.fetchTimeZones).toHaveBeenCalledTimes(1);
    });
    it('ignores a different Store catalog response and blocks stale draft writes without discarding it', async () => {
        let finish!: (zones: string[]) => void;
        mocks.fetchTimeZones.mockReturnValueOnce(new Promise(resolve => { finish = resolve; }));
        const wrapper = await open(true, 'Europe/Vienna');
        await wrapper.get('input[placeholder="tickets.event_title_placeholder"]').setValue('Keep this draft');
        await wrapper.setProps({ store: { id: 'store-b', name: 'Other synthetic store', timezone: 'UTC' } } as never);
        finish(zones); await flushPromises();
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('');
        expect((wrapper.get('input[placeholder="tickets.event_title_placeholder"]').element as HTMLInputElement).value).toBe('Keep this draft');
        await button(wrapper, 'tickets.virtual').trigger('click'); await submit(wrapper);
        expect(mocks.createEvent).not.toHaveBeenCalled();
        await button(wrapper, 'common.cancel').trigger('click');
        await button(wrapper, 'tickets.create_event').trigger('click'); await flushPromises();
        expect((wrapper.get('#ticket-event-time-zone').element as HTMLSelectElement).value).toBe('UTC');
        expect(mocks.fetchTimeZones).toHaveBeenLastCalledWith('store-b');
    });
});
