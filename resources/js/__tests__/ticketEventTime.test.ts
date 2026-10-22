import { describe, expect, it } from 'vitest';
import { eventDatePayload, eventTimeDraft, eventWallTime, rebaseEventTimeDraft, supportsEventTimeZone } from '../utils/ticketEventTime';
import type { TicketEvent } from '../store/tickets';
const event = {
    id: 'event-a', title: 'Synthetic event', storeId: 'local-store', eventType: 'Physical', timeZone: 'Europe/Bratislava',
    startDate: '2026-11-28T07:00:42.123456Z', endDate: '2026-11-29T10:00:37.456789Z', hasMaximumCapacity: false, eventState: 'Active',
} satisfies TicketEvent;
describe('event venue time formatting and civil write intent', () => {
    it('formats the saved instant in the venue regardless of browser zone, including midnight', () => {
        expect(eventWallTime(event.startDate, 'Physical', event.timeZone)).toBe('2026-11-28T08:00');
        expect(eventWallTime('2026-11-27T23:00:00Z', 'Physical', event.timeZone)).toBe('2026-11-28T00:00');
        expect(eventWallTime('2026-11-28T23:30:00Z', 'Physical', 'Pacific/Auckland')).toBe('2026-11-29T12:30');
        expect(eventWallTime('2026-11-28T07:00:00Z', 'Physical', 'Asia/Kathmandu')).toBe('2026-11-28T12:45');
    });
    it('does not normalize civil strings through the browser zone or guess DST offsets', () => {
        const input = { startDate: '2026-03-08T02:30', endDate: '', baselineStart: '', baselineEnd: '' };
        expect(eventDatePayload(input, 'Physical', 'Europe/Bratislava', false)).toEqual({ timeZone: 'Europe/Bratislava', startLocal: '2026-03-08T02:30' });
        expect(eventDatePayload({ ...input, startDate: '2026-03-29T02:30' }, 'Physical', event.timeZone, false)).toMatchObject({ startLocal: '2026-03-29T02:30' });
        expect(eventDatePayload({ ...input, startDate: '2026-10-25T02:30' }, 'Physical', event.timeZone, false)).toMatchObject({ startLocal: '2026-10-25T02:30' });
    });
    it('omits all unchanged dates, preserving original UTC subsecond precision and overlap instant', () => {
        const draft = eventTimeDraft(event);
        expect(eventDatePayload(draft, 'Physical', event.timeZone, true)).toEqual({ timeZone: event.timeZone });
        const folded = { ...event, startDate: '2026-10-25T01:30:42.123456Z' };
        expect(eventDatePayload(eventTimeDraft(folded), 'Physical', event.timeZone, true)).not.toHaveProperty('startLocal');
    });
    it('changes a clean zone display while keeping saved instants and omitting date writes', () => {
        const rebased = rebaseEventTimeDraft(eventTimeDraft(event), event, 'Physical', 'America/New_York');
        expect(rebased.startDate).toBe('2026-11-28T02:00');
        expect(eventDatePayload(rebased, 'Physical', 'America/New_York', true)).toEqual({ timeZone: 'America/New_York' });
    });
    it('keeps edited civil time when changing zone, while independently rebasing the clean end', () => {
        const draft = { ...eventTimeDraft(event), startDate: '2026-11-28T10:00' };
        const rebased = rebaseEventTimeDraft(draft, event, 'Physical', 'America/New_York');
        expect(rebased.startDate).toBe('2026-11-28T10:00');
        expect(rebased.endDate).toBe('2026-11-29T05:00');
        expect(eventDatePayload(rebased, 'Physical', 'America/New_York', true)).toEqual({ timeZone: 'America/New_York', startLocal: '2026-11-28T10:00' });
    });
    it('clears an edited end explicitly and leaves an untouched missing end omitted', () => {
        expect(eventDatePayload({ ...eventTimeDraft(event), endDate: '' }, 'Physical', event.timeZone, true)).toEqual({ timeZone: event.timeZone, endLocal: null });
        expect(eventDatePayload(eventTimeDraft({ ...event, endDate: null }), 'Physical', event.timeZone, true)).toEqual({ timeZone: event.timeZone });
    });
    it('uses explicit UTC for legacy missing zone without selecting a guessed venue zone', () => {
        const legacy = { ...event, timeZone: null };
        expect(eventTimeDraft(legacy).startDate).toBe('2026-11-28T07:00');
        const chosen = rebaseEventTimeDraft(eventTimeDraft(legacy), legacy, 'Physical', 'Europe/Bratislava');
        expect(chosen.startDate).toBe('2026-11-28T08:00');
        expect(eventDatePayload(chosen, 'Physical', 'Europe/Bratislava', true)).toEqual({ timeZone: 'Europe/Bratislava' });
    });
    it('preserves instants on type changes and leaves virtual time zones unpersisted', () => {
        const virtual = rebaseEventTimeDraft(eventTimeDraft(event), event, 'Virtual', event.timeZone);
        expect(eventDatePayload(virtual, 'Virtual', event.timeZone, true)).toEqual({});
        const physical = rebaseEventTimeDraft(virtual, event, 'Physical', event.timeZone);
        expect(physical.startDate).toBe('2026-11-28T08:00');
        expect(eventDatePayload(physical, 'Physical', event.timeZone, true)).toEqual({ timeZone: event.timeZone });
    });
    it('rejects invalid IANA names and does not fall back to the viewer zone for stored invalid metadata', () => {
        expect(supportsEventTimeZone('Invalid/Not_A_Zone')).toBe(false);
        expect(supportsEventTimeZone('Europe/Bratislava')).toBe(true);
        expect(eventWallTime(event.startDate, 'Physical', 'Invalid/Not_A_Zone')).toBe('2026-11-28T07:00');
    });
});
