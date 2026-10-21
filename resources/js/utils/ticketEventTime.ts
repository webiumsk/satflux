import type { TicketEvent, TicketEventWrite } from '../store/tickets';

export type EventTimeKind = 'Physical' | 'Virtual';
export interface EventTimeDraft {
    startDate: string;
    endDate: string;
    baselineStart: string;
    baselineEnd: string;
}

export function supportsEventTimeZone(zone: string): boolean {
    if (!zone) return false;
    try {
        new Intl.DateTimeFormat('en', { timeZone: zone }).format(0);
        return true;
    } catch {
        return false;
    }
}

/** Format an instant into civil input without ever parsing the civil text as a Date. */
export function eventWallTime(instant: string | null | undefined, kind: EventTimeKind, zone?: string | null): string {
    if (!instant) return '';
    const date = new Date(instant);
    if (!Number.isFinite(date.getTime())) throw new Error('Invalid event instant');
    const formatter = new Intl.DateTimeFormat('en-CA', {
        ...(kind === 'Physical' ? { timeZone: zone && supportsEventTimeZone(zone) ? zone : 'UTC' } : {}),
        calendar: 'gregory', numberingSystem: 'latn', hourCycle: 'h23',
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
    });
    const parts = Object.fromEntries(formatter.formatToParts(date).map(part => [part.type, part.value]));
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

export function eventTimeDraft(event?: TicketEvent | null): EventTimeDraft {
    const startDate = event ? eventWallTime(event.startDate, event.eventType, event.timeZone) : '';
    const endDate = event ? eventWallTime(event.endDate, event.eventType, event.timeZone) : '';
    return { startDate, endDate, baselineStart: startDate, baselineEnd: endDate };
}

/** Zone/type changes preserve saved instants; edited civil text retains its explicit intent. */
export function rebaseEventTimeDraft(
    draft: EventTimeDraft, event: TicketEvent | null, kind: EventTimeKind, zone?: string | null,
): EventTimeDraft {
    const baselineStart = event ? eventWallTime(event.startDate, kind, zone) : '';
    const baselineEnd = event ? eventWallTime(event.endDate, kind, zone) : '';
    return {
        startDate: draft.startDate === draft.baselineStart ? baselineStart : draft.startDate,
        endDate: draft.endDate === draft.baselineEnd ? baselineEnd : draft.endDate,
        baselineStart,
        baselineEnd,
    };
}

/** Physical civil-to-UTC conversion belongs exclusively to the provider, including DST validation. */
export function eventDatePayload(
    draft: EventTimeDraft, kind: EventTimeKind, zone: string, existing: boolean,
): Pick<TicketEventWrite, 'timeZone' | 'startDate' | 'endDate' | 'startLocal' | 'endLocal'> {
    const data: Pick<TicketEventWrite, 'timeZone' | 'startDate' | 'endDate' | 'startLocal' | 'endLocal'> = {};
    if (kind === 'Physical') {
        if (zone) data.timeZone = zone;
        if (!existing || draft.startDate !== draft.baselineStart) data.startLocal = draft.startDate;
        if (draft.endDate !== draft.baselineEnd || (!existing && draft.endDate)) data.endLocal = draft.endDate || null;
    } else {
        if (!existing || draft.startDate !== draft.baselineStart) data.startDate = draft.startDate ? new Date(draft.startDate).toISOString() : '';
        if (draft.endDate !== draft.baselineEnd || (!existing && draft.endDate)) data.endDate = draft.endDate ? new Date(draft.endDate).toISOString() : null;
    }
    return data;
}
